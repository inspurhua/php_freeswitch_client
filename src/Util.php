<?php

use Medoo\Medoo;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class Util
{
    static private $instance;
    public $config;
    public $db;
    public $redis;
    public $log;
    public $Redis_Prefix = '';

    private function __construct($config)
    {
        $this->config = $config;
        $this->db = new Medoo($config['db']);
        ini_set('default_socket_timeout', -1);  //redis socket不超时
        $this->redis = new Redis();
        $this->redis->pconnect($config['redis']['host'], $config['redis']['port']);
        $this->redis->auth($config['redis']['password']);
        $this->Redis_Prefix = isset($config['redis']['prefix']) ? $config['redis']['prefix'] : '';
        if (isset($config['log'])) {
            $this->log = new Logger($config['log']);
            $handler = new RotatingFileHandler("./logs/{$config['log']}.log", 10, Logger::DEBUG);
            $handler->setFormatter(new LineFormatter("[%datetime%]%message%\n", 'H:i:s u'));
            $this->log->pushHandler($handler);
        }
    }

    //防止克隆对象
    private function __clone()
    {

    }

    static public function getInstance($config)
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public static function Raw($string, array $map = [])
    {
        return Medoo::raw($string, $map);
    }

    public function GetUserByFid($session)
    {
        return $this->db->get('sys_user', 'name', ['socket_session' => $session]);
    }

    public function GetFidByUser($user)
    {
        return intval($this->db->get('sys_user', 'socket_session', ['name' => $user]));
    }

    public function UpdateAgentStatus(array $data, $name, $login = false)
    {
        $data['agent_status_update_time'] = Medoo::raw('localtimestamp(0)');
        $where['name'] = $name;
        $this->db->update('sys_user', $data, $where);
        if ($login) {
            //第一次登录修改状态
            $this->db->insert('sys_agent_status_log', [
                'name' => $where['name'],
                'status' => $data['agent_status']
            ]);
            return;
        }
        //登录后修改状态
        $id = $this->db->get('sys_agent_status_log', 'id', [
            'name' => $where['name'],
            'ORDER' => ['start_stamp' => 'DESC'],
            'LIMIT' => 1
        ]);

        if ($id) {
            $this->db->update('sys_agent_status_log', [
                'end_stamp' => Medoo::raw('localtimestamp(0)'),
                'duration' => Medoo::raw('extract(epoch FROM (localtimestamp(0)-start_stamp))')
            ], ['id' => $id]);
        }

        $this->db->insert('sys_agent_status_log', [
            'name' => $where['name'],
            'status' => $data['agent_status']
        ]);
    }

    public function UpdateAgentStatusByExt(array $data, $ext)
    {
        $data['agent_status_update_time'] = Medoo::raw('localtimestamp(0)');
        $where['name'] = $this->db->get('sys_user', 'name', ['ext' => $ext]);
        $this->db->update('sys_user', $data, $where);

        //登录后修改状态
        $id = $this->db->get('sys_agent_status_log', 'id', [
            'name' => $where['name'],
            'ORDER' => ['start_stamp' => 'DESC'],
            'LIMIT' => 1
        ]);

        if ($id) {
            $this->db->update('sys_agent_status_log', [
                'end_stamp' => Medoo::raw('localtimestamp(0)'),
                'duration' => Medoo::raw('extract(epoch FROM (localtimestamp(0)-start_stamp))')
            ], ['id' => $id]);
        }

        $this->db->insert('sys_agent_status_log', [
            'name' => $where['name'],
            'status' => $data['agent_status']
        ]);
        return $where['name'];
    }

    public static function guid()
    {
        mt_srand((double)microtime() * 10000);
        $str = strtolower(md5(uniqid(rand(), true)));

        $uuid = substr($str, 0, 8) . '-'
            . substr($str, 8, 4) . '-'
            . substr($str, 12, 4) . '-'
            . substr($str, 16, 4) . '-'
            . substr($str, 20, 12);
        return $uuid;
    }

    public static function executeAppStr($uuid, $app, $args = null, $option = null)
    {
        $guid = self::guid();
        $cmd = "sendmsg {$uuid}
        Event-UUID: {$guid}
        event-lock: true
        call-command: execute
        execute-app-name: {$app}
        ";
        if ($option) {
            $cmd .= $option;
        }
        if ($args) {
            $len = strlen($args);
            $cmd .= "content-type: text/plain
            content-length: {$len}
            
            {$args}
            ";
        }
        return $cmd;
    }

    public static function executeAppStr2($uuid, $app, $args = null, $option = null)
    {
        $cmd = "sendmsg {$uuid}
        event-lock: true
        call-command: execute
        execute-app-name: {$app}
        ";
        if ($args) {
            $cmd .= "execute-app-arg: {$args}
            ";
        }
        if ($option) {
            $cmd .= $option . "\n";
        }

        return $cmd;
    }

    public static function MakeTaskMessage($name, $method, $params = [])
    {
        return json_encode([$name, [
            "method" => $method,
            "params" => $params
        ]], JSON_UNESCAPED_UNICODE);
    }

    public static function MakeMessage($result, $method, $message = '', $data = '')
    {
        return json_encode([
            'result' => $result,
            'method' => $method,
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
    }

    public static function MakeResultMessage($name, $message)
    {
        return json_encode([
            $name,
            $message
        ], JSON_UNESCAPED_UNICODE);
    }
}
