<?php
    namespace Octo;

    call_user_func(function () {
        session_start();

        path("app",     realpath(__DIR__ . '/../tests'));
        path("base",    path('app'));
        path("storage", path('app') . '/storage');

        systemBoot(path('app'));

        Octo::cli();
    });
