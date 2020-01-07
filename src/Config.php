<?php
return [
    'db' => [
        'database_type' => 'pgsql',
        'database_name' => 'freeswitch',
        'server' => '127.0.0.1',
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
    ],
    //此处必须是绝对的ip地址，不能是127.0.0.1，而且host也写绝对的
    'ws' => [
        'domain'=>'ws.dmchina.com.cn',
        'host' => '127.0.0.1',
        'port' => 8501,
        'path' => '/message'
    ],
    'es' => [
        'host' => '127.0.0.1',
        'port' => 8021,
        'password' => 'ClueCon'
    ],
    'cti' => [
        'rule0' => 'select telnum from tproject_sample where id = :id',
        'rule1' => 'select telnum1 from tproject_sample where id = :id',
        'rule2' => 'select telnum2 from tproject_sample where id = :id',
        'rule3' => 'select telnum3 from tproject_sample where id = :id',
        'rule4' => 'select telnum4 from tproject_sample where id = :id',
        'rule5' => 'select telnum5 from tproject_sample where id = :id'
    ],
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => '1qaz2wsx?',
        'prefix' => '',
    ]
];
