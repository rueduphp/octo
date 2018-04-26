<?php
namespace Octo;

return [
    'defaultConnection' => 'default',
    'connections' => [

        'default' => [
            'hosts' => [
                [
                    'host'   => 'localhost',
                    'port'   => 9200,
                    'scheme' => null,
                    'user'   => null,
                    'pass'   => null,
                ],
            ],

            'logging' => false,
            'logPath' => storage_path('logs/elasticsearch.log'),
            'logLevel' => \Monolog\Logger::INFO,
            'retries' => null,
            'sniffOnStart' => false,
            'httpHandler' => null,
            'connectionPool' => null,
            'connectionSelector' => null,
            'serializer' => null,
            'connectionFactory' => null,
            'endpoint' => null,
        ],
    ],
];