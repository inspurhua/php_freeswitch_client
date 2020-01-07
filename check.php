<?php
$php= "/usr/local/php/bin/php";
$cmds = [
    'msgserver' => "nohup {$php} /root/msgserver.php > /dev/null 2>&1 &",
    'CHANNEL_CREATE' => "nohup {$php} /root/eventsocket.php CHANNEL_CREATE >> /root/logs/c.log 2>&1 &",
    'CHANNEL_HANGUP ' => "nohup {$php} /root/eventsocket.php CHANNEL_HANGUP >> /root/logs/h.log  2>&1 &",
    'CHANNEL_ANSWER' => "nohup {$php} /root/eventsocket.php CHANNEL_ANSWER >> /root/logs/a.log 2>&1 &",
    'CHANNEL_HANGUP_COMPLETE' => "nohup {$php} /root/eventsocket.php CHANNEL_HANGUP_COMPLETE >> /root/logs/hc.log 2>&1 &",
    'erp.jar event' => 'nohup java -jar /root/erp.jar event > /dev/null 2>&1 &',
    'erp.jar result' => 'nohup java -jar /root/erp.jar result > /dev/null 2>&1 &',
    'pool.php 1' => 'nohup {$php} /root/pool.php 1 > /dev/null 2>&1 &',
    'pool.php 2' => 'nohup {$php} /root/pool.php 2 > /dev/null 2>&1 &',
    'pool.php 3' => 'nohup {$php} /root/pool.php 3 > /dev/null 2>&1 &',
    'pool.php 4' => 'nohup {$php} /root/pool.php 4 > /dev/null 2>&1 &',
    'pool.php 5' => 'nohup {$php} /root/pool.php 5 > /dev/null 2>&1 &',
    'pool.php 6' => 'nohup {$php} /root/pool.php 6 > /dev/null 2>&1 &',
    'pool.php 7' => 'nohup {$php} /root/pool.php 7 > /dev/null 2>&1 &',
    'pool.php 8' => 'nohup {$php} /root/pool.php 8 > /dev/null 2>&1 &',
];

foreach ($cmds as $cmd => $args) {
    $num = intval(exec("ps aex|grep '$cmd'|grep -v grep|wc -l"));
    if ($num == 0) {
        shell_exec($args);
    }
}