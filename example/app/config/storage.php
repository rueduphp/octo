<?php
return [
    'default' => 'local',

    'cloud' => 's3',

    'disks' => [

        'local' => [
            'driver'    => 'local',
            'root'      => Octo\storage_path('app'),
        ],

        'public' => [
            'driver'        => 'local',
            'root'          => Octo\storage_path('app/public'),
            'url'           => App\Facades\Config::get('app.url') . '/storage',
            'visibility'    => 'public',
        ],

        's3' => [
            'driver'    => 's3',
            'key'       => pkey('aws.id'),
            'secret'    => pkey('aws.key'),
            'region'    => pkey('aws.region'),
            'bucket'    => pkey('aws.bucket'),
            'url'       => pkey('aws.url'),
        ],

    ],

];
