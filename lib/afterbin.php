<?php
    namespace Octo;

    require_once __DIR__  . '/lib.php';
    systemBoot(__DIR__);

    Timer::start();

    if ($dir = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : null) {
        lib('later')->after($dir);
    }
