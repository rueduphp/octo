<?php
    $PDOoptions = [
        PDO::ATTR_CASE                 => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE              => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS         => PDO::NULL_NATURAL,
        PDO::ATTR_DEFAULT_FETCH_MODE   => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES    => false,
        PDO::ATTR_EMULATE_PREPARES     => false
    ];

    $pdo = new PDO('sqlite::memory:', null, null, $PDOoptions);

    return [
        'paths' => [
            'migrations' => __DIR__ . '/tests/migrations',
            'seeds'      => __DIR__ . '/tests/seeds'
        ],
        'environments' => [
            'default_database' => 'testing',
            'testing'      => [
                'name'       => 'testing',
                'connection' => $pdo
            ]
        ]
    ];
