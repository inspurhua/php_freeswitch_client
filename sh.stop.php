<?php
/**
 * Created by PhpStorm.
 * User: zhanghua
 * Date: 2018/8/6
 * Time: 12:26
 */

$p = [
    "msgserver.php",
    "erp.jar",
    "pool.php",
"eventsocket.php",
"monitor.php"
];

foreach ($p as $row){
    exec("ps -eaf |grep \"{$row}\" | grep -v \"grep\"| awk '{print $2}'|xargs kill -9");
}
