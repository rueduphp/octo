<?php
namespace App\Modules;

use App\Models\User;
use Octo\Module;
use Octo\Facades\Route;
use Octo\Facades\Redirect;

class StaticModule extends Module
{
    /**
     * @throws \Octo\Exception
     * @throws \ReflectionException
     */
    public function routes()
    {
        view()->addNamespace('mail', __DIR__ . '/../views/mails');

        dic(404, function () {
            return view('static.404', ['pageTitle' => 'Error 404']);
        });

        Route::get('/', function () {
            return view(
                'static.home', [
                    'pageTitle'     => 'Super Page',
                    'subPageTitle'  => 'Super Title'
                ]
            );
        }, 'home');

        Route::get('login', function () {
            if (!auth()->logged()) {
                return view(
                    'static.login', [
                        'pageTitle' => 'Login'
                    ]
                );
            } else {
                $url = session()->previousUrl();

                if ($url === fullRoute('login')) {
                    $url = route('home');
                }

                return Redirect::to($url);
            }
        }, 'login');

        Route::post('login', function () {
            /** @var null|User $user */
            $user = repo('user')->login(...inputs('email', 'password', 'remember'));

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
            flash()->success('Good Bye');

            return Redirect::route('login');
        }, 'logout');
    }
}
