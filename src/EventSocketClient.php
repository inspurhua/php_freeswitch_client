<?php
include_once 'EventJsonResponse.php';
function getExtByChannelName($channel)
{
    $ok = preg_match('/sofia\/internal\/(\d+)@/u', $channel, $m);
    return $ok ? $m[1] : '';
}

function removeFromQueue($uuid, $db)
{
    $name = $db->query(/** @lang text */
        "select name from sys_skill where array_position(queue,'{$uuid}')>0"
    )->fetchColumn();
    if (!empty($name)) {
        $db->query(/** @lang text */
            "update sys_skill set queue=array_remove(queue,'{$uuid}')"
        );
    }
}

class EventSocketClient
{
    private $client;

    public function __construct($event)
    {
        $config = include __DIR__ . '/Config.php';
        $config = array_merge($config, ['log' => 'eventsocket-' . str_replace(' ', '-', $event)]);
        $util = Util::getInstance($config);
        $this->client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        $this->client->on('connect', function () use ($util) {
            $util->log->debug("EventSocketClient Connected OK");
        });
        $this->client->on("error", function () use ($util) {
            $util->log->debug("EventSocketClient Error");
        });
        $this->client->on("close", function () use ($util) {
            $util->log->debug("EventSocketClient Connection close");
        });
        $this->client->on("receive", function (swoole_client $cli, $data) use ($util, $event) {
            $util->log->debug("eventsocket receive:" . $data);

            if (strpos($data, "auth/request") > -1) {
                $cli->send("auth {$util->config['es']['password']}\n\n");
                $cli->send("event json {$event}\n\n");
            } else {
                $ejr = new EventJsonResponse($data);
                $ejr_data = $ejr->get_content();

                foreach ($ejr_data as $item) {
                    $body = json_decode($item, true);
                    $send_msg = '';
                    //处理事件
                    switch ($body["Event-Name"]) {
                        case "CHANNEL_HANGUP":
                            //如果是坐席修改此人状态是话后
                            $ext = getExtByChannelName($body['variable_channel_name']);
                            $util->log->debug('_CHANNEL_HANGUP:' . $ext);
                            if (strlen($ext) == 4) {
                                $name = $util->UpdateAgentStatusByExt([
                                    'agent_status' => 4,
                                    'uuid' => '',
                                    'last_hangup_time' => $util::Raw('localtimestamp(3)'),
                                ], $ext);
                                //发送消息
                                $send_msg = Util::MakeResultMessage($name, Util::MakeMessage(0, 'updateagentstatus', $name, '4'));
                            }

                            $uuid = $body['Unique-ID'];
                            removeFromQueue($uuid, $util->db);

                            //如果此人三方了第三人，则挂断第三人
                            $third = $util->db->get('sys_three', ['id', 'third'], [
                                'me' => $uuid,
                                'oper' => 3,
                            ]);
                            if ($third) {
                                $cli->send("api uuid_kill {$third['third']}\n\n");
                                $util->log->debug("eventsocket ->:" . "api uuid_kill {$third['third']}\n\n");
                                $util->db->delete('sys_three', ['id' => $third['id']]);
                            }

                            // //转接的时候，B挂机，给C弹屏
                            // $app = isset($body['variable_current_application']) ? $body['variable_current_application'] : '';
                            // if ($app == "att_xfer") {
                            //     $app_data = isset($body['variable_current_application_data']) ? $body['variable_current_application_data'] : '';
                            //     $ok = preg_match('/user\/(\d+)[@]*/', $app_data, $m);
                            //     if ($ok) {
                            //         $skill = isset($body['variable_skill']) ? $body['variable_skill'] : '';
                            //         $caller = isset($body['variable_caller']) ? $body['variable_caller'] : '';
                            //         $send_msg = Util::MakeResultMessage($m[1],
                            //             Util::MakeMessage(0, 'ring', $caller, $skill));
                            //     }
                            // }
                            break;
                        case "CHANNEL_CREATE":
                            //修改此人通话中
                            $ext = getExtByChannelName($body['variable_channel_name']);
                            $util->log->debug('_CHANNEL_CREATE:' . $ext);
                            if (strlen($ext) == 4) {
                                $name = $util->UpdateAgentStatusByExt([
                                    'agent_status' => 3,
                                    'uuid' => $body['Unique-ID'],
                                ], $ext);
                                //发送消息
                                $send_msg = Util::MakeResultMessage($name, Util::MakeMessage(0, 'updateagentstatus', $name, '3'));
                            }
                            break;
                        case "CHANNEL_ANSWER":
                            //外呼测试可以
                            $ext = getExtByChannelName($body['variable_channel_name']);
                            $from = isset($body['variable_sip_from_user'])?$body['variable_sip_from_user']:$body["Caller-Caller-ID-Number"];
                            $util->log->debug('_CHANNEL_ANSWER:' . $ext);
                            //外呼
                            if (strlen($ext) == 4 && isset($body['variable_bridge_channel'])) {
                                $name = $util->UpdateAgentStatusByExt([
                                    'agent_status' => 5,
                                ], $ext);
                                //发送消息
                                $send_msg = Util::MakeResultMessage($name, Util::MakeMessage(
                                    0,
                                    'updateagentstatus',
                                    $name,
                                    '5'
                                ));
                            } elseif (empty($ext) && isset($body['variable_bridge_channel'])) {
                                $ext = getExtByChannelName($body['variable_bridge_channel']);
                                $name = $util->UpdateAgentStatusByExt([
                                    'agent_status' => 5,
                                ], $ext);
                                //发送消息
                                $send_msg = Util::MakeResultMessage($name, Util::MakeMessage(
                                    0,
                                    'updateagentstatus',
                                    $name,
                                    '5'
                                ));
                            } elseif (strlen($ext) == 4 && isset($body["Other-Leg-Channel-Name"])) {
                                $name = $util->UpdateAgentStatusByExt([
                                    'agent_status' => 5,
                                ], $ext);
                                //发送消息
                                $send_msg = Util::MakeResultMessage($name, Util::MakeMessage(
                                    0,
                                    'updateagentstatus',
                                    $name,
                                    '5'
                                ));
                            } elseif (strlen($ext) == 4 && $from =="0000") {
                                    //啥也不干
                            } else {
                                $name = $util->UpdateAgentStatusByExt([
                                    'agent_status' => 5,
                                ], $ext);
                                //发送消息
                                $send_msg = Util::MakeResultMessage($name, Util::MakeMessage(
                                    0,
                                    'updateagentstatus',
                                    $name,
                                    '5'
                                ));
                            }

                            break;
                        case "CHANNEL_HANGUP_COMPLETE":
                            //cdr 话单
                            $cdr = isset($body['_body']) ? $body['_body'] : '';
                            $cdr = stripslashes($cdr);
                            if ($cdr) {
                                $cdr = str_replace('<nolocal:aleg_uuid>', '<aleg_uuid>', $cdr);
                                $cdr = str_replace('</nolocal:aleg_uuid>', '</aleg_uuid>', $cdr);
                                $xml = new \SimpleXMLElement($cdr);

                                $variables = $xml->variables;
                                $uuid = (string)$variables->uuid;
                                $iammyd = (string)$variables->iammyd;
                                $agent = urldecode((string)$variables->agent);
                                $direction = (string)$variables->direction;
                                $sip_from_user = (string)$variables->sip_from_user;
                                $sip_to_user = (string)$variables->sip_to_user;
                                $skill = isset($variables->skill) ? (string)$variables->skill : '';
                                $default_gateway_group = isset($variables->default_gateway_group) ? (string)$variables->default_gateway_group : '';
                                $data = [
                                    'direction' => $direction,
                                    'start_stamp' => urldecode((string)$variables->start_stamp),
                                    'answer_stamp' => urldecode((string)$variables->answer_stamp) ?: null,
                                    'bridge_stamp' => urldecode((string)$variables->bridge_stamp) ?: null,
                                    'end_stamp' => urldecode((string)$variables->end_stamp),
                                    'duration' => (int)$variables->duration,
                                    'billsec' => (int)$variables->billsec,
                                    'waitsec' => (int)$variables->waitsec,
                                    'hold_accum_seconds' => (int)$variables->hold_accum_seconds,
                                    'hangup_cause' => (string)$variables->hangup_cause,
                                    'sip_hangup_disposition' => (string)$variables->sip_hangup_disposition,
                                    'uuid' => $uuid,
                                    'agent' => $agent,
                                    'skill' => $skill,
                                ];

                                if (!empty($skill)) {
                                    //呼入处理
                                    $data['direction'] = 'inbound';
                                    $data['caller'] = $sip_from_user;
                                    $data['callee'] = (string)$variables->callee ?: '';
                                } else {
                                    //呼出处理
                                    $data['direction'] = 'outbound';
                                    $data['gateway'] = $default_gateway_group;
                                    $data['caller'] = $sip_from_user;
                                    $data['callee'] = $sip_to_user;
                                    if ($sip_from_user === '0000' && strlen($sip_to_user) === 4) {
                                        $data['caller'] = $sip_to_user;
                                        $data['callee'] = array_pop(explode('/', (string)$variables->bridge_channel));
                                    }
                                }

                                $id = $util->db->get('z_callstat', 'id', ['uuid' => $uuid]);
                                $util->log->debug("EventSocketClient PUSH[has]:$id");
                                if ($id) {
                                    $util->db->update('z_callstat', $data, ['uuid' => $uuid]);
                                } else {
                                    if ($iammyd !== 'true') {
                                        $util->db->insert('z_callstat', $data);
                                    }
                                }
                            }
                            break;
                        default:
                            break;
                    }
                    $util->log->debug("EventSocketClient PUSH[event]:$send_msg");
                    $send_msg && $util->redis->lPush($util->Redis_Prefix . 'event', $send_msg);
                }
            }
        });

        $this->client->connect($util->config['es']['host'], $util->config['es']['port']);
    }
}
