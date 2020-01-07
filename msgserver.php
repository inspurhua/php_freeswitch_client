<?php
chdir(__DIR__);
ini_set('memory_limit','512M');
require('./vendor/autoload.php');

$config = include __DIR__ . '/src/Config.php';
$config = array_merge($config,['log'=>'msgserver']);
$util = Util::getInstance($config);

$app = new Ratchet\App($util->config['ws']['domain'],$util->config['ws']['port'],$util->config['ws']['host']);
$app->route('/message', new MsgServer($util),['*']);
$app->run();

//打印一下各个php文件获取到的$db资源是否是一个id，如果是去掉单例模式
