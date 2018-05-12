<?php
namespace App\Modules;

use Octo\Module;
use Octo\Facades\Route;

class StaticModule extends Module
{
    public function routes()
    {
        Route::get('/', function () {dd(\Octo\getSession()->guard());
            return $this->render('static.home');
        }, 'home');
    }
}