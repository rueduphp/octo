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
        'key'    => pkey('stripe.key'),
        'secret' => pkey('stripe.secret'),
    ],

    'facebook' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => fullRoute('social') . 'facebook/callback',
    ],

    'google' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => fullRoute('social') . 'google/callback',
    ],

    'twitter' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => fullRoute('social') . 'twitter/callback',
    ],

    'linkedin' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => fullRoute('social') . 'linkedin/callback',
    ],

    'spotify' => [
        'client_id' => env('SPOTIFY_KEY'),
        'client_secret' => env('SPOTIFY_SECRET'),
        'redirect' => fullRoute('social') . 'spotify/callback',
    ],

    'github' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => fullRoute('social') . 'github/callback',
    ],
];
