<?php
namespace App\Providers;

use function Octo\in_paths;

class Paths
{
    public function handler()
    {
        $paths = in_paths();

        $dirApp = realpath(__DIR__ . '/../');

        $paths['app']       = $dirApp;
        $paths['base']      = realpath($dirApp . '/../');
        $paths['public']    = realpath($dirApp . '/../public');
        $paths['config']    = $dirApp . '/config';
        $paths['storage']   = $dirApp . '/storage';
        $paths['database']  = $dirApp . '/storage/database';
        $paths['cache']     = $dirApp . '/storage/cache';
        $paths['sessions']  = $dirApp . '/storage/sessions';
        $paths['log']       = $dirApp . '/storage/log';
        $paths['lang']      = $dirApp . '/lang';
        $paths['views']     = $dirApp . '/views';
    }
}
