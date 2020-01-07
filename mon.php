<?php
/**
 * Created by PhpStorm.
 * User: zhanghua
 * Date: 2018/12/4
 * Time: 10:47
 */

$ip = '10.10.20.67';

include_once './vendor/autoload.php';

$db = new \Medoo\Medoo([
    'database_type' => 'pgsql',
    'database_name' => 'freeswitch',
    'server' => '10.10.20.65',
    'username' => 'freeswitch',
    'password' => '1qaz2wsx?',
    'command' => [
        'set search_path to public'
    ],
    'option' => [
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_PERSISTENT => true
    ],
    'logging' => true,
]);
function mail_msg($text){
    $transport = (new Swift_SmtpTransport('smtp.bwing.com.cn', 25))
      ->setUsername('hua.zhang@bwing.com.cn')
      ->setPassword('Sdfihua1')
    ;

    // Create the Mailer using your created Transport
    $mailer = new Swift_Mailer($transport);

    // Create a message
    $message = (new Swift_Message('Freeswitch服务器出现问题'))
      ->setFrom(['hua.zhang@bwing.com.cn' => '张华'])
      ->setTo(['aczhanghua@qq.com','caibin.zhang@dmchina.com.cn','yanwei.qi@dmchina.com.cn'])
      ->setBody($text)
    ;

    // Send the message
    $result = $mailer->send($message); 
    return $result;
}
function process(){
    $data = [];
    exec("pidof php", $data);
    if(count($data)==1){
        $a = count(explode(' ', $data[0]));
        echo $a . ',';
        if($a<14){
            //error
            mail_msg('php 进程少了');
        }
    }else{
        //error
        mail_msg('php 进程没了');
    }
    $data = [];
    exec("pidof java", $data);
    if(count($data)==1){
        if(count(explode(' ', $data[0]))<2){
            //error
            mail_msg('java 进程少了');
        }
    }else{
        //error
        mail_msg('java 进程没了');
    }
    $data = [];
    exec("pidof freeswitch", $data);
    if(count($data)==1){
        if(count(explode(' ', $data[0]))<1){
            //error
            mail_msg('freeswitch 进程没了');
        }
    }else{
        //error
        mail_msg('freeswitch 进程没了');
    }
}

function mem()
{
    $data = [];
    exec("free -m", $data);
    $hd = preg_split('/\s+/', $data[1]);
    return ['create_at' => date('Y-m-d H:i:s', time()), 'total' => $hd[1], 'used' => $hd[2], 'free' => $hd[3]];
}

function hd()
{
    $data = [];
    exec("df -H", $data);
    $hd = preg_split('/\s+/', $data[1]);
    $total = str_replace('G', '', $hd[1]);
    $used = str_replace('G', '', $hd[2]);
    $free = str_replace('G', '', $hd[3]);
    return ['create_at' => date('Y-m-d H:i:s', time()), 'total' => $total, 'used' => $used, 'free' => $free];
}

function cpu()
{
    $data = [];
    exec("uptime", $data);
    $hd = preg_split('/\s+/', $data[0]);

    $count = count($hd);

    $ten = str_replace(',', '', $hd[$count - 1]);
    $five = str_replace(',', '', $hd[$count - 2]);
    $one = str_replace(',', '', $hd[$count - 3]);

    return ['create_at' => date('Y-m-d H:i:s', time()), 'one' => floatval($one), 'five' => floatval($five), 'ten' => floatval($ten)];
}


$mem_data = mem();
$mem_data['ip'] = $ip;

$db->insert('monitor_mem', $mem_data);

$hd_data = hd();
$hd_data['ip'] = $ip;
$db->insert('monitor_hd', $hd_data);

$cpu_data = cpu();
$cpu_data['ip'] = $ip;
$db->insert('monitor_cpu', $cpu_data);

function net($name)
{
    $data = [];
    exec("cat /proc/net/dev", $data);

    foreach ($data as $key => $value) {
        $hd = preg_split('/\s+/', $value);
        if ($hd[1] == "$name:") {
            return ['create_at' => date('Y-m-d H:i:s', time()), 'i' => $hd[2], 'o' => $hd[10]];
        }
    }
    return [];
}

$net_name = 'eth0';
switch ($ip) {
    case '10.10.20.65':
    case '10.10.20.66':
        $net_name = 'em1';
        break;
    default:
        $net_name = 'eth0';
        break;
}
$net_data = net($net_name);
$net_data['ip'] = $ip;

$last_data = $db->query("select * from monitor_net where ip = '$ip' order by id desc limit 1")->fetch();
$db->insert('monitor_net', $net_data);
$id = $db->id();
$last_i = is_array($last_data) ? intval($last_data['i']) : 0;
$last_o = is_array($last_data) ? intval($last_data['o']) : 0;
$db->update('monitor_net', ['idiff' => round((intval($net_data['i']) - $last_i)/1024),
    'odiff' => round((intval($net_data['o']) - $last_o)/1024),
], ['id' => $id]);

process();
