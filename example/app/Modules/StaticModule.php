<?php
namespace App\Modules;

use Octo\Facades\Form;
use Octo\Module;
use Octo\Facades\Route;

class StaticModule extends Module
{
    public function routes()
    {
        dd(Form::open());

        Route::get('/', function () {
            return $this->render('static.home');
        }, 'home');
    }
}