<?php
namespace App\Providers;

use App\Facades\Config;
use App\Services\Container;
use App\Services\Log;
use App\Services\ViewCache;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\SocialiteManager;
use Monolog\Logger as Monolog;
use Mmanos\Search\Search;
use function Octo\bladeFactory;
use function Octo\lang_path;
use Octo\Limiter;
use function Octo\log_path;
use Octo\Mailable;
use function Octo\setTranslator;
use function Octo\views_path;

class Facades
{
    /**
     * @throws \ReflectionException
     */
    public function handler()
    {
        dic()::singleton('view', function () {
            return bladeFactory([views_path()]);
        });

        dic()::singleton('logservice', function () {
            $log = new Log(new Monolog('octo'));
            $log->useDailyFiles(
                log_path('octo.log'),
                5,
                'debug'
            );

            return $log;
        });

        l()->singleton(ViewCache::class, function () {
            return new ViewCache;
        });

        l()->singleton('request', function () {
            $request = new Request;
            $request->setLaravelSession(l('session.store'));

            return $request;
        });

        l()->singleton(Factory::class, function ($app) {
            return new SocialiteManager($app);
        });

        dic('social', l(Factory::class));

        l()->singleton('view', function () {
            return dic('view');
        });

        l()->singleton('session', function ($app) {
            return new SessionManager($app);
        });

        l()->singleton(\Illuminate\Contracts\Auth\Access\Gate::class, function () {
            return auth();
        });

        l()->singleton('files', function () {
            return new Filesystem;
        });

        l()->singleton('session.store', function ($app) {
            return $app->make('session')->driver();
        });

        makeFacade('Container', function () {
            return new Container;
        });

        makeFacade('Redys', function () {
            return l('redis');
        });

        makeFacade('Lite', function () {
            return dic('db')->connection('sqlite');
        });

        makeFacade('Schema', function () {
            return \Octo\getSchema();
        });

        makeFacade('Db', function () {
            return l('db');
        });

        makeFacade('Config', function () {
            return l('config');
        });

        makeFacade('Search', function () {
            return new Search;
        });

        makeFacade('Mail', function () {
            return new Mailable;
        });

        makeFacade('Event', function () {
            return dic('eventer');
        });

        makeFacade('Cache', function () {
            $config = Config::get('app');

            return cacheService($config['cache_ttl'] ?? 60, 'app');
        });

        makeFacade('Files', function () {
            return l('files');
        });

        makeFacade('Session', function () {
            return session();
        });

        makeFacade('Auth', function () {
            return auth();
        });

        makeFacade('Flash', function () {
            return flash();
        });

        makeFacade('Log', function () {
            return dic('logservice');
        });

        makeFacade('Lang', function () {
            return setTranslator(lang_path(), locale());
        });

        makeFacade('Throttle', function () {
            $cache = cacheService(60, 'throttle');

            return new Limiter($cache);
        });
    }
}
