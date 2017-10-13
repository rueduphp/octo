<?php
    namespace Octo;

    require_once realpath(__DIR__) . '/../vendor/autoload.php';

    session_start();

    define('OCTO_STANDALONE', true);

    path("app", realpath(__DIR__));
    path("base", path('app'));
    path("storage", path('app') . '/storage');

    systemBoot(path('app'));

    require_once realpath(__DIR__) . '/classes.php';

    Octo::cli();
    Later::listen();
