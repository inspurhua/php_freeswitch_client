<?php

class ClientPool
{
    private $client;
    private $util;

    public function __construct($id)
    {
        $config = include __DIR__ . '/Config.php';
        $config = array_merge($config, ['log' => 'pool' . $id]);
        $util = Util::getInstance($config);
        $this->util = $util;
        $this->util->redis->client('SETNAME', 'pool' . $id);
        $this->client = new swoole_client(SWOOLE_SOCK_TCP);
        if (!$this->client->connect($util->config['es']['host'], $util->config['es']['port'], -1)) {
            $util->log->error("connect failed. Error: {$this->client->errCode}");
        }
        $data = $this->client->recv();
        $resp = new FsResponse($data);

        if ($resp->getContentType() == 'auth/request') {
            $this->client->send("auth {$util->config['es']['password']}\n\n");
            $this->client->recv();
        }

        while (1) {
            try {
                $task_queue = $util->Redis_Prefix . (($id == 'debug') ? 'debug_task' : 'task');
                $task = $util->redis->brPop([$task_queue], 0);
                $str_task_data = strval($task[1]);
                $util->log->debug("ClientPool BRPOP[task] $str_task_data");

                //接收到数据$str_task_data
                //检查是否task包含debug数据，如果是直接放入debug_task,然后continue
                if ($task_queue == $util->Redis_Prefix . 'task') {
                    $keywords = $util->db->get('debug', 'keyword', ['enable' => 1, 'name' => 'pool']);
                    if (!empty($keywords)) {
                        $exists = false;
                        foreach (explode('|', $keywords) as $item) {
                            if (strpos($str_task_data, $item) > -1) {
                                $exists = true;
                                break;
                            }
                        }
                        if ($exists) {
                            $util->redis->lPush($util->Redis_Prefix . 'debug_task', $str_task_data);
                            continue;
                        }
                    }
                }

                $send_data = json_decode($str_task_data, true);

//            [$name, [
//                "method" => 'call',
//                "params" => [$caller, $callee, $gateway_group]
//            ]]

                $name = $send_data[0];//命令发起人
                $method = $send_data[1]['method'];
                $params = $send_data[1]['params'];
                $msg = '';
                switch ($method) {
                    case 'call':
                        $caller = $params[0];
                        $callee = $params[1];
                        $gateway_group = $params[2];
                        if (strlen($callee) > 4) {
                            if ($gateway_group == 'undefined') {
                                $gateway_group = $util->db->query("select default_gateway_group from directory where username = '$caller'")->fetchColumn();
                            }
                            $cmd = "api originate {origination_caller_id_number=0000,origination_caller_id_name=DMCC}user/{$caller} 9{$callee},{$gateway_group} XML default\n\n";
                        } else {
                            $cmd = "api originate user/{$caller} {$callee} XML default\n\n";
                        }

                        $util->log->debug("ClientPool execute: $cmd");
                        $ret = trim($this->sendCmd($cmd));
                        $util->log->debug("ClientPool result: $ret");
                        $id = -1;
                        if (strlen($callee) > 4 && strlen($ret) == 36) {
                            if ($ret) {
                                $util->db->insert('z_callstat', [
                                    'agent' => $name,
                                    'caller' => $caller,
                                    'callee' => $callee,
                                    'uuid' => $ret,
                                    'direction' => 'outbound'
                                ]);

                                $id = $util->db->id();
                            }
                        }
                        $msg = Util::MakeMessage(($id > 0 ? 0 : -1), 'call', ($id > 0 ? '呼叫成功' : '呼叫失败' . $ret), $id);
                        break;
                    case 'hold':
                        $uuid = $util->db->get('sys_user', 'uuid', ['name' => $name]);
                        if ($uuid) {
                            $cmd = "api uuid_hold {$uuid}\n\n";
                            $util->log->debug("ClientPool execute: $cmd");
                            $ret = $this->sendCmd($cmd, 0);
                            $id = -1;
                            if ($ret) {
                                //记录hold表
                                $util->db->insert('sys_agent_hold_log', [
                                    'name' => $name,
                                    'uuid' => $uuid
                                ]);

                                $id = $util->db->id();
                            }
                            $msg = Util::MakeMessage(($id > 0 ? 0 : -1), 'hold', ($id > 0 ? '保持成功' : '保持失败' . $ret), $id);
                        } else {
                            $msg = Util::MakeMessage(-1, 'hold', '不在通话中', 0);
                        }
                        break;
                    case 'unhold':
                        $uuid = $util->db->get('sys_user', 'uuid', ['name' => $name]);
                        if ($uuid) {
                            $cmd = "api uuid_hold off {$uuid}\n\n";
                            $util->log->debug("ClientPool execute: $cmd");
                            $ret = $this->sendCmd($cmd, 0);

                            $id = -1;
                            if ($ret) {
                                $id = $util->db->get('sys_agent_hold_log', 'id', [
                                    'name' => $name,
                                    'ORDER' => ['start_stamp' => 'DESC'],
                                    'LIMIT' => 1
                                ]);

                                if ($id) {
                                    $util->db->update('sys_agent_hold_log', [
                                        'end_stamp' => $util::Raw('localtimestamp(0)'),
                                        'duration' => $util::Raw('extract(epoch FROM (localtimestamp(0)-start_stamp))')
                                    ], ['id' => $id]);
                                }
                            }
                            $msg = Util::MakeMessage(($id > 0 ? 0 : -1), 'unhold', ($id > 0 ? '恢复成功' : '恢复失败' . $ret), $id);
                        } else {
                            $msg = Util::MakeMessage(-1, 'unhold', '不在通话中', 0);
                        }
                        break;
                    case 'hangup':
                        $uuid = $util->db->get('sys_user', 'uuid', ['name' => $name]);
                        if ($uuid) {
                            $cmd = "api uuid_kill {$uuid}\n\n";
                            $util->log->debug("ClientPool execute: $cmd");
                            $ret = $this->sendCmd($cmd, 0);
                            $msg = Util::MakeMessage(($ret ? 0 : -1), 'hangup', ($ret ? '挂机成功' : '挂机失败' . $ret), 0);
                        } else {
                            $msg = Util::MakeMessage(-1, 'hangup', '不在通话中', 0);
                        }
                        break;
                    case 'answer':
                        $uuid = $util->db->get('sys_user', 'uuid', ['name' => $name]);
                        if ($uuid) {
                            $cmd = "api uuid_answer {$uuid}\n\n";
                            $util->log->debug("ClientPool execute: $cmd");
                            $ret = $this->sendCmd($cmd, 0);
                            $msg = Util::MakeMessage(($ret ? 0 : -1), 'answer', ($ret ? '接听成功' : '接听失败' . $ret), 0);
                        } else {
                            $msg = Util::MakeMessage(-1, 'answer', '还没有会话', 0);
                        }
                        break;
                    case 'three_way':
                        $three = $params[0];
                        if ($three) {
                            $cmd = "api originate {origination_caller_id_number={$name}}user/{$three} &park\n\n";
                            $util->log->debug("ClientPool execute: $cmd");
                            $uuid = $this->sendCmd($cmd);

                            //me
                            $target_uuid = $util->db->get('sys_user', 'uuid', ['name' => $name]);



                            //other,实际测试获取不到
                            //$cmd = "api uuid_getvar $target_uuid variable_bridge_uuid \n\n";
                            //$util->log->debug("ClientPool execute: $cmd");
                            //$other_uuid = $this->sendCmd($cmd);
                            //insert
                            $util->db->insert('sys_three', [
                                'oper' => 3,
                                'me' => $target_uuid,
                               // 'other' => $other_uuid,
                                'third' => $uuid
                            ]);
                            $cmd = $util::executeAppStr($uuid, 'three_way', $target_uuid);
                            $util->log->debug("ClientPool execute: $cmd");
                            $this->client->send($cmd);
                            $data = $this->client->recv();
                            $resp = new FsResponse($data);

                            if ($resp->getReplyText() == '+OK') {
                                $msg = Util::MakeMessage(0, 'three_way', '三方通话成功', 0);
                            } else {
                                $msg = Util::MakeMessage(-1, 'three_way', '三方通话失败', 0);
                            }
                        } else {
                            $msg = Util::MakeMessage(-1, 'three_way', '找不到第三方', 0);
                        }
                        break;
                    case 'eavesdrop':
                        $target_uuid = $util->db->get('sys_user', 'uuid', ['name' => $params[0]]);
                        if ($target_uuid) {
                            $cmd = "api originate {origination_caller_id_number=0000,origination_caller_id_name=DMCC}user/{$name} &eavesdrop({$target_uuid})\n\n";
                            $util->log->debug("ClientPool execute: $cmd");

                            $ok = $this->sendCmd($cmd, 0);
                            if ($ok) {
                                $msg = Util::MakeMessage(0, 'eavesdrop', '监听成功', 0);
                            } else {
                                $msg = Util::MakeMessage(-1, 'eavesdrop', '监听失败', 0);
                            }
                        } else {
                            $msg = Util::MakeMessage(-1, 'eavesdrop', '找不到正在进行的两方通话', 0);
                        }
                        break;
                    case 'whisper':
                        $target_uuid = $util->db->get('sys_user', 'uuid', ['name' => $params[0]]);
                        if ($target_uuid) {
                            $cmd = "api originate {origination_caller_id_number=0000,origination_caller_id_name=DMCC}user/{$name} 'queue_dtmf:w2@500,eavesdrop:{$target_uuid}' inline\n\n";
                            $util->log->debug("ClientPool execute: $cmd");

                            $ok = $this->sendCmd($cmd, 0);
                            if ($ok) {
                                $msg = Util::MakeMessage(0, 'whisper', '私语成功', 0);
                            } else {
                                $msg = Util::MakeMessage(-1, 'whisper', '私语失败', 0);
                            }
                        } else {
                            $msg = Util::MakeMessage(-1, 'whisper', '找不到正在进行的两方通话', 0);
                        }
                        break;
                    case 'att_xfer':
                        //这里暂时通过app处理，还可以通过uuid_transfer+ xml dialplan解决
                        $uuid = $util->db->get('sys_user', 'uuid', ['name' => $name]);
                        if ($uuid) {
                            $ext = $util->db->get('sys_user', 'ext', ['name' => $params[0]]);
                            //让他弹屏，获取本通道上的变量，谈到转接通道上
                            $skill = $this->getVar($uuid, 'skill');
                            $util->log->debug($skill);
                            $caller = $this->getVar($uuid, 'caller');
                            $util->log->debug($caller);

//                            $cmd = $util->executeAppStr2($uuid, "set", "hangup_after_bridge=true");
//                            $this->client->send($cmd);

                            $cmd = $util->executeAppStr2($uuid, "att_xfer", "user/$ext");
                            $this->client->send($cmd);
//                            $this->setVar($uuid,'att_xfer_to',$ext);
//                            $cmd = $util->executeAppStr($uuid, "execute_extension", "att_xfer XML default");
//                            $this->client->send($cmd);
                            if ($this->recvOK()) {
                                $send_msg = Util::MakeResultMessage($ext,
                                    Util::MakeMessage(0, 'ring', $caller, $skill));
                                $util->redis->lPush($util->Redis_Prefix . 'result', $send_msg);
                                $msg = Util::MakeMessage(0, 'att_xfer', '转接成功', 0);
                            } else {
                                $msg = Util::MakeMessage(-1, 'att_xfer', '转接失败', 0);
                            }
                        } else {
                            $msg = Util::MakeMessage(-1, 'att_xfer', '转接失败', 0);
                        }
                        break;
                    case 'transfer_ivr':
                        $uuid = $util->db->get('sys_user', 'uuid', ['name' => $name]);
                        $file = $util->db->get('sys_ivr', 'file', ['code' => $params[0]]);
                        if ($uuid) {
                            $cmd = "api uuid_transfer {$uuid} -bleg lua:$file inline\n\n";
                            $util->log->debug("ClientPool execute: $cmd");
                            $ret = $this->sendCmd($cmd, 0);
                            $id = -1;
                            if ($ret) {
                                $id = 1;
                            }
                            $msg = Util::MakeMessage(($id > 0 ? 0 : -1), 'transfer_ivr', ($id > 0 ? '转IVR成功' : '转IVR失败' . $ret), $id);
                        } else {
                            $msg = Util::MakeMessage(-1, 'transfer_ivr', '不在通话中', 0);
                        }
                        break;
                }
                $msg = Util::MakeResultMessage($name, $msg);

                $util->log->debug("ClientPool PUSH[result] $msg");
                $util->redis->lPush($util->Redis_Prefix . 'result', $msg);

            } catch (Exception $e) {
                $util->log->debug("pool.php {$id}异常退出:" . $e->getMessage());
                break;
            }

        }
    }

    public function sendCmd($cmd, $index = 1)
    {
        $this->client->send($cmd);
        $data = $this->client->recv();
        $this->util->log->debug("Receive1 from FreeSwitch $data");
        $resp = new FsResponse($data);
        $ret = $resp->getContent();

        if (!$ret[0]) {
            $data = $this->client->recv();
            $this->util->log->debug("Receive2 from FreeSwitch $data");
            $resp = new FsResponse($data);
            $ret = $resp->getContent();
        }

        if ($index < 2) {
            return $ret[$index];
        } else {
            return $ret;
        }
    }

    public function getVar($uuid, $varname)
    {
        $this->client->send("api uuid_getvar $uuid $varname\n\n");
        $data = $this->client->recv();
        list($header, $body) = explode("\n\n", $data);
        return $body;
    }

    public function setVar($uuid, $varname, $value)
    {
        $this->client->send("api uuid_setvar $uuid $varname $value\n\n");
        $data = $this->client->recv();
        return (strpos($data, '+OK') > -1);
    }

    public function recvOK()
    {
        $data = $this->client->recv();
        return (strpos($data, '+OK') > -1);
    }
}
