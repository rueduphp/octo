<?php
    namespace Octo;

    ini_set('error_reporting', E_ALL);
    ini_set('display_errors', true);

    require_once __DIR__ . '/../vendor/autoload.php';

    call_user_func(function () {
        session_start();

        path("app",     realpath(__DIR__ . '/../tests'));
        path("base",    path('app'));
        path("storage", path('app') . '/storage');

        try {
            systemBoot(path('app'));
        } catch (\Exception $e) {
            $error = $e->getMessage() . ' [' . $e->getFile() . ' on line ' . $e->getLine() . ']';
            Cli::show($error, 'ERROR');
        }

        Octo::cli();
    });
