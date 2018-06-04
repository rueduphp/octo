<?php
return [

    'mailgun' => [
        'domain' => '',
        'secret' => '',
    ],

    'mandrill' => [
        'secret' => '',
    ],

    'ses' => [
        'key'    => '',
        'secret' => '',
        'region' => 'us-east-1',
    ],

    'stripe' => [
        'model'  => \App\Models\User::class,
        'key'    => "",
        'secret' => "",
    ],

    'facebook' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => '',
    ],

    'google' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => fullRoute('social.google.handle'),
    ],

    'twitter' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => '',
    ],

    'linkedin' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => '',
    ],

    'github' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => '',
    ],
];