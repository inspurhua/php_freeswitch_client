<?php
chdir(__DIR__);
ini_set('memory_limit','512M');
require('./vendor/autoload.php');
$event = isset($argv[1])?$argv[1]:'';

if (empty($event)){
    die;
}
//CHANNEL_CREATE CHANNEL_ANSWER CHANNEL_HANGUP CHANNEL_HANGUP_COMPLETE
new EventSocketClient($event);
