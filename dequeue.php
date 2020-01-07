<?php
chdir(__DIR__);
ini_set('memory_limit', '512M');
require('./vendor/autoload.php');

$config = include __DIR__ . '/src/Config.php';
$db = Util::getInstance($config)->db;


$data = $db->query(/** @lang text */
    "select unnest(queue) as uuid from sys_skill")->fetchAll();

foreach ($data as $row) {
    $uuid = $row['uuid'];
    $sql = /** @lang text */
        "update sys_skill set queue=array_remove(queue,'{$uuid}')
where not exists (select * from channels where uuid = '{$uuid}')
";
    $db->query($sql);
}
