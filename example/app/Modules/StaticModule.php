<?php
namespace App\Modules;

use App\Facades\Redirect;
use App\Facades\Route;
use App\Facades\View;
use App\Models\User;
use App\Services\Auth;
use App\Services\Module;

class StaticModule extends Module
{
    /**
     * @throws \Octo\Exception
     * @throws \ReflectionException
     */
    public function routes()
    {
        View::addNamespace('mail', __DIR__ . '/../views/mails');

        $this->dic(404, function () {
            return $this->view('static.404', ['pageTitle' => 'Error 404']);
        });

        Route::get('/', function () {
            $pageTitle = 'Super Page';
            $subPageTitle = 'Super Title';

            return $this->view('static.home', get_defined_vars());
        }, 'home');

        Route::get('user/{user}', [$this, 'user']);

        Route::get('login', function () {
            if (!$this->auth()->logged()) {
                $this->pageTitle = 'Login';

                return $this->view('static.login');
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
            $user = $this->repo(userRepository())->login(...$this->inputs('email', 'password', 'remember'));
            $this->redirectWith(\Octo\viewParams()->toArray());

            if (null === $user) {
                return Redirect::error('Unknown account')->route('login');
            }

            $redirect = Redirect::success('Welcome');

            if ($url = $this->request->session()->pull('redirect_url')) {
                return $redirect->to($url);
            }

            return $redirect->home();
        }, 'log');

        Route::post('logout', function () {
            if ($this->auth()->logged()) {
                $this->repo(userRepository())->logout();

                return Redirect::success('Good Bye')->route('login');
            }

            return Redirect::error('You are not logged')->back();
        }, 'logout');
    }

    /**
     * @param User $user
     * @return \Illuminate\View\Factory|string
     */
    public function user(User $user)
    {
        return $this->view('static.home');
    }
}
