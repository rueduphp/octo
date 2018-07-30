<?php
namespace App\Modules;

use App\Facades\Redirect;
use App\Facades\Route;
use App\Services\Module;
use Illuminate\Http\RedirectResponse;
use Octo\ModuleMiddleware;

class SocialLoginModule extends Module
{
    /**
     * @throws \ReflectionException
     */
    public function routes()
    {
        $providers = ['github', 'facebook', 'google', 'twitter', 'linkedin', 'spotify'];

        foreach ($providers as $provider) {
            Route::get('social/' . $provider, [$this, 'redirector'], 'social.' . $provider);
            Route::get('social/' . $provider . '/callback', [$this, 'handle'], 'social.handle.' . $provider);
        }
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
            return Redirect::error('A problem occured with ' . ucfirst($provider))->route('login');
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
        lc(['services' => $services]);
        addConfig('services');
    }
}
