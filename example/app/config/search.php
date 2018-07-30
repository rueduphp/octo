<?php

return [
    'default' => 'algolia',

    'default_index' => 'default',

    'connections' => [

        'zend' => [
            'driver' => 'zend',
            'path'   => Octo\storage_path('search'),
        ],

        'elasticsearch' => [
            'driver' => 'elasticsearch',
            'config' => [
                'hosts' => ['es:9200'],
            ],
        ],

        'algolia' => [
            'driver' => 'algolia',
            'config' => [
                'application_id' => '',
                'admin_api_key'  => '',
            ],
        ],
    ],
];
