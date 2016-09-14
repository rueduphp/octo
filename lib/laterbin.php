<?php
    namespace Octo;

    require_once __DIR__  . '/../public/admin/standalone.php';

    Timer::start();

    lib('later')->listen();
