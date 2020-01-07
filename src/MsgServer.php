<?php
/**
 * Created by PhpStorm.
 * User: zhanghua
 * Date: 2018/8/6
 * Time: 21:58
 * 如果要支持不同坐席登录不同分机，需要在sys_user 中加入字段extension分机
 */

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;


class MsgServer implements MessageComponentInterface
{
    protected $clients;
    protected $util;

    public function __construct(Util $util)
    {

        $this->util = $util;
    }


    public function onOpen(ConnectionInterface $conn)
    {
        list ($name, $ext) = $this->getNameExt($conn);
        $this->util->log->debug("open:$name($ext)");
        $this->clients[$name] = $conn;
        if (empty($name) || empty($ext)) {
            $conn->send(Util::MakeMessage(-1, 'login', '未提供必须的参数！'));
            $conn->close();
            return;
        }

        //看看除我之外别人登陆了吗
        $has_other = $this->util->db->has('sys_user', ['name[!]' => $name, 'ext' => $ext]);
        if ($has_other) {
            $conn->send(Util::MakeMessage(-1, 'login', '当前分机被其他坐席上登录了！'));
            $conn->close();
            return;
        }

        if (substr($name, 0, 7) == 'watcher') {
            return;
        }

        $this->util->UpdateAgentStatus([
            'socket_session' => Util::guid(),
            'agent_status' => 7,
            'ext' => $ext
        ], $name, true);

        $conn->send(Util::MakeMessage(0, 'sign', '签入成功！'));
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        list($name, $ext) = $this->getNameExt($from);

        $this->util->log->debug("message:$name($ext):$msg");
        //{"method": "call",  "params": ["callernum","calleenum,viagateway","rule"]}
        $data = json_decode($msg, true);
        if (!is_array($data)) {
            $from->send(Util::MakeMessage(0, 'unknown', '消息格式不正确！'));
            return;
        }

        switch ($data['method']) {
            case 'help':
                //参数[to_name,to_msg]
                $to_name = $data['params'][0];
                $to_msg = $data['params'][1];
                if (isset($this->clients[$to_name])) {
                    $this->clients[$to_name]->send($to_msg);
                }
                break;
            case 'ping':
                $from->send(Util::MakeMessage(0, 'pong', $name, ''));
                break;
            case 'updateagentstatus':
                $status = $data['params'][0];
                $this->util->UpdateAgentStatus([
                    'agent_status' => $status
                ], $name);
                $from->send(Util::MakeMessage(0, 'updateagentstatus', $name, $status));
                break;
            case 'call':
                $caller = $data['params'][0];

                list($callee, $gateway) = explode(',', $data['params'][1]);
                $rule = $data['params'][2];
                if ($rule) {
                    $callee = $this->util->db->query($this->util->config['cti']['rule' . $rule],
                        [':id' => $callee])->fetchColumn();
                }

                //打电话,发送消息
                $send_data = Util::MakeTaskMessage($name, 'call', [$caller, $callee, $gateway]);
                $this->util->log->debug("WS SERVER PUSH[task]:$send_data");
                $this->util->redis->lPush($this->util->Redis_Prefix .'task', $send_data);
                break;
            case 'hold':
                $send_data = Util::MakeTaskMessage($name, 'hold');
                $this->util->log->debug("WS SERVER PUSH[task]:$send_data");
                $this->util->redis->lPush($this->util->Redis_Prefix .'task', $send_data);
                break;
            case 'unhold':
                $send_data = Util::MakeTaskMessage($name, 'unhold');
                $this->util->log->debug("WS SERVER PUSH[task]:$send_data");
                $this->util->redis->lPush($this->util->Redis_Prefix .'task', $send_data);
                break;
            case 'three_way':
                $agent = $data['params'][0];//要跟谁三方通话
                $send_data = Util::MakeTaskMessage($name, 'three_way', [$agent]);
                $this->util->log->debug("WS SERVER PUSH[task]:$send_data");
                $this->util->redis->lPush($this->util->Redis_Prefix .'task', $send_data);
                break;
            case 'eavesdrop':
                $agent = $data['params'][0];//要监听谁
                $send_data = Util::MakeTaskMessage($name, 'eavesdrop', [$agent]);
                $this->util->log->debug("WS SERVER PUSH[task]:$send_data");
                $this->util->redis->lPush($this->util->Redis_Prefix .'task', $send_data);
                break;
            case 'whisper':
                $agent = $data['params'][0];//要跟谁私语
                $send_data = Util::MakeTaskMessage($name, 'whisper', [$agent]);
                $this->util->log->debug("WS SERVER PUSH[task]:$send_data");
                $this->util->redis->lPush($this->util->Redis_Prefix .'task', $send_data);
                break;
            case 'att_xfer':
                $agent = $data['params'][0];//要跟谁分机，
                $send_data = Util::MakeTaskMessage($name, 'att_xfer', [$agent]);
                $this->util->log->debug("WS SERVER PUSH[task]:$send_data");
                $this->util->redis->lPush($this->util->Redis_Prefix .'task', $send_data);
                break;
            case 'transfer_ivr':
                $code = $data['params'][0];//ivr code
                $send_data = Util::MakeTaskMessage($name, 'transfer_ivr', [$code]);
                $this->util->log->debug("WS SERVER PUSH[task]:$send_data");
                $this->util->redis->lPush($this->util->Redis_Prefix .'task', $send_data);
                break;
            case 'hangup':
                $agent = $data['params'][0];////$name发出指令要 让$agent挂机
                $send_data = Util::MakeTaskMessage($name, 'hangup', [$agent]);
                $this->util->log->debug("WS SERVER PUSH[task]:$send_data");
                $this->util->redis->lPush($this->util->Redis_Prefix .'task', $send_data);
                break;
            case 'answer':
                $agent = $data['params'][0];//$name发出指令要 让$agent接听
                $send_data = Util::MakeTaskMessage($name, 'answer', [$agent]);
                $this->util->log->debug("WS SERVER PUSH[task]:$send_data");
                $this->util->redis->lPush($this->util->Redis_Prefix .'task', $send_data);
                break;
            case 'yuyue':
                $endtime = strtotime($data['params'][0]);
                if (!$endtime) {
                    $from->send(Util::MakeMessage(-1, 'yuyue', '提供的时间格式不对，预约失败！', ''));
                }
                $endtime = ($endtime - time()) * 1000;
                $content = strtotime($data['params'][1]);
                swoole_timer_after($endtime, function () use ($from, $content) {
                    $from->send(Util::MakeMessage(0, 'yuyue', $content, ''));
                });
                break;

        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        list($name, $ext) = $this->getNameExt($conn);
        $this->util->log->debug("close:$name($ext)");
        if (substr($name, 0, 7) == 'watcher') {
            return;
        }
        $this->util->UpdateAgentStatus([
            'socket_session' => '',
            'agent_status' => 8,
            'ext' => ''
        ], $name);
        unset($this->clients[$name]);
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $conn->close();
    }

    private function getNameExt(ConnectionInterface $conn, $type = 'both')
    {
        $name = $ext = '';
        $uri = $conn->httpRequest->getUri();
        $query_str = $uri->getQuery();
        foreach (explode('&', $query_str) as $item) {
            list ($key, $value) = explode('=', $item);
            if ($key == 'name') {
                $name = $value;
            } else if ($key == 'ext') {
                $ext = $value;
            }
        }
        $ret = '';
        switch ($type) {
            case 'both':
                $ret = [$name, $ext];
                break;
            case 'name':
                $ret = $name;
                break;
            case 'ext':
                $ret = $ext;
                break;
        }
        return $ret;
    }
}


