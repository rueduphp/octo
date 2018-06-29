<?php
namespace App\Modules;

use App\Models\User;
use function Octo\faker;
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
        $this->view()->addNamespace('mail', __DIR__ . '/../views/mails');

        $this->dic(404, function () {
            return $this->view('static.404', ['pageTitle' => 'Error 404']);
        });

        Route::get('/', function () {
            return $this->view(
                'static.home', [
                    'pageTitle'     => 'Super Page',
                    'subPageTitle'  => 'Super Title'
                ]
            );
        }, 'home');

        Route::get('user/{user}', [$this, 'user']);

        Route::get('login', function () {
            if (!$this->auth()->logged()) {
                return $this->view(
                    'static.login', [
                        'pageTitle' => 'Login'
                    ]
                );
            } else {
                $url = $this->session()->previousUrl();

                if ($url === $this->fullRoute('login')) {
                    $url = $this->route('home');
                }

                return Redirect::to($url);
            }
        }, 'login');

        Route::post('login', function () {
            /** @var null|User $user */
            $user = $this->repo('user')->login(...$this->inputs('email', 'password', 'remember'));
            $this->redirectWith(\Octo\viewParams()->toArray());

            if (null === $user) {
                $this->flash()->error('Unknown account');

                return Redirect::route('login');
            }

            $this->flash()->success('Welcome');

            if ($url = $this->request->session()->pull('redirect_url')) {
                return Redirect::to($url);
            }

            return Redirect::home();
        }, 'log');

        Route::post('logout', function () {
            $this->repo('user')->logout();
            $this->flash()->success('Good Bye');

            return Redirect::route('login');
        }, 'logout');
    }

    public function user(User $user)
    {
        return $this->view(
            'static.home', [
                'pageTitle'     => 'Super Page',
                'subPageTitle'  => 'Super Title'
            ]
        );
    }
}
