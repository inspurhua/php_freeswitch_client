<?php
chdir(__DIR__);
ini_set('memory_limit','512M');
require('./vendor/autoload.php');
$id = isset($argv[1])?$argv[1]:'';
new ClientPool($id);
