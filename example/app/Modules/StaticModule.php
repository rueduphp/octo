<?php
namespace App\Modules;

use App\Models\User;
use Octo\Module;
use Octo\Facades\Route;
use Octo\Facades\Redirect;

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
            return $this->render(
                'static.home', [
                    'pageTitle' => 'Super Page',
                    'subPageTitle' => 'Super Title'
                ]
            );
        }, 'home');

        Route::get('login', function () {
            return $this->render(
                'static.login', [
                    'pageTitle' => 'Login'
                ]
            );
        }, 'login');

        Route::post('login', function () {
            /** @var null|User $user */
            $user = repo('user')->login(request('email'), request('password'), request('remember'));

            if (null === $user) {
                flash()->error('Unknown account');

                return Redirect::route('login');
            }

            flash()->success('Welcome');

            if ($url = $this->request->session()->pull('redirect_url')) {
                return Redirect::to($url);
            }

            return Redirect::home();
        }, 'log');

        Route::post('logout', function () {
            repo('user')->logout();

            return Redirect::to('login');
        }, 'logout');
    }
}