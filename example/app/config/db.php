<?php

return [
    'default'   => 'mysql',
    'mysql'     => [
        'driver'    => 'mysql',
        'host'      => 'mysql',
        'port'      => '3306',
        'database'  => 'octo',
        'username'  => 'octo',
        'password'  => 'octo',
    ],
    'sqlite' => [
        'driver' => 'sqlite',
        'database' => Octo\database_path('octo.db'),
        'prefix' => '',
    ],
];
