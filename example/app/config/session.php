<?php

return [
    'driver' => 'file',
    'lifetime' => 600,
    'ttl' => 7200,
    'expire_on_close' => true,
    'encrypt' => false,
    'files' => Octo\storage_path('sessions'),
    'connection' => null,
    'table' => 'sessions',
    'lottery' => [2, 100],
    'cookie' => 'app_session',
    'path' => '/',
    'domain' => null,
    'secure' => false,
];
