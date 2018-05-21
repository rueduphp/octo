<?php
namespace App\Modules;

use Octo\Module;
use Octo\Facades\Session;
use Octo\Facades\Route;

class StaticModule extends Module
{
    /**
     * @throws \ReflectionException
     */
    public function routes()
    {
        dic(404, function () {
            return $this->render('static.404', ['pageTitle' => 'Error 404']);
        });

        Route::get('/', function () {
//            Session::set('success', '<h1>COOL</h1>');
            return $this->render(
                'static.home', [
                    'breadcrumb' => true,
                    'pageTitle' => 'Super Page',
                    'subPageTitle' => 'Super Title'
                ]
            );
        }, 'home');
    }
}