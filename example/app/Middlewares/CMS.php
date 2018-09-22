<?php
namespace App\Middlewares;

class CMS
{
    public function process($request, $next)
    {
        $app = main();
        $paths = $app->component('paths');
        $config = $app->component('config');
    }
}
