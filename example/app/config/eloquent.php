<?php
return [
    'mysql' => [
        'driver'        => 'mysql',
        'host'          => 'mysql',
        'port'          => 3306,
        'database'      => 'octo',
        'username'      => 'octo',
        'password'      => 'octo',
        'unix_socket'   => '',
        'charset'       => 'utf8',
        'collation'     => 'utf8_unicode_ci',
        'prefix'        => '',
        'strict'        => true,
        'engine'        => null,
    ],
    'sqlite' => [
        'driver' => 'sqlite',
        'database' => Octo\database_path('octo.db'),
        'prefix' => '',
    ],
];
