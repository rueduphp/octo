<?php
namespace App\Modules;

use Illuminate\Http\RedirectResponse;
use Octo\Module;
use Octo\Facades\Route;
use Octo\Facades\Redirect;
use Octo\ModuleMiddleware;

class SocialLoginModule extends Module
{
    /**
     * @throws \ReflectionException
     */
    public function routes()
    {
        Route::get('social/github', [$this, 'redirector'], 'social.github');
        Route::get('social/github/callback', [$this, 'handle']);

        Route::get('social/facebook', [$this, 'redirector'], 'social.facebook');
        Route::get('social/facebook/callback', [$this, 'handle']);

        Route::get('social/google', [$this, 'redirector'], 'social.google');
        Route::get('social/google/callback', [$this, 'handle'], 'social.google.handle');

        Route::get('social/twitter', [$this, 'redirector'], 'social.twitter');
        Route::get('social/twitter/callback', [$this, 'handle']);

        Route::get('social/linkedin', [$this, 'redirector'], 'social.linkedin');
        Route::get('social/linkedin/callback', [$this, 'handle']);
    }

    /**
     * @throws \ReflectionException
     */
    public function redirector(SocialMiddleware $middleware)
    {
        $provider = $this->request->segment(2);

        try {
            /** @var RedirectResponse $response */
            $response = dic('social')->driver($provider)->redirect();

            return Redirect::to($response->getTargetUrl());
        } catch (\Exception $e) {
            return Redirect::route('login');
        }

    }

    /**
     * @throws \ReflectionException
     */
    public function handle()
    {
        $provider = $this->request->segment(2);
    }
}

class SocialMiddleware extends ModuleMiddleware
{
    public function before()
    {
        $services = include \Octo\config_path('services.php');
        l('config')->set(['services' => $services]);
        addConfig('services');
    }
}
