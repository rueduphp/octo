<?php
    namespace Octo;

    define('OCTO_STANDALONE', true);

    path("app", realpath(__DIR__ . '/../tests'));
    path("base", path('app'));
    path("storage", path('app') . '/storage');

    systemBoot(path('app'));

    Octo::cli();
