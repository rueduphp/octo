<?php
namespace App\Providers;

use App\Facades\Event;
use App\Facades\Config;
use App\Services\Container;
use App\Services\Dispatcher;
use App\Services\Log;
use App\Services\Queue;
use App\Services\ViewCache;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Illuminate\View\Compilers\BladeCompiler as Blader;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\SocialiteManager;
use Monolog\Logger as Monolog;
use Mmanos\Search\Search;
use function Octo\bladeCompiler;
use function Octo\bladeFactory;
use Octo\FastRequest;
use function Octo\lang_path;
use Octo\Limiter;
use function Octo\log_path;
use Octo\Mailable;
use function Octo\setTranslator;
use function Octo\views_path;
use SocialiteProviders\Manager\Contracts\Helpers\ConfigRetrieverInterface;
use SocialiteProviders\Manager\Helpers\ConfigRetriever;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Spotify\SpotifyExtendSocialite;

class Facades
{
    /**
     * @throws \ReflectionException
     */
    protected function social()
    {
        Event::listen('social.called', SpotifyExtendSocialite::class . '@handle');

        Event::fire('social.called');
    }

    /**
     * @throws \ReflectionException
     */
    public function handler()
    {
        l()->singleton(
            \Illuminate\Contracts\Foundation\Application::class,
            function () {
                return l();
            }
        );

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

        l()->singleton(
            \Illuminate\Contracts\Console\Kernel::class,
            \App\Console\Kernel::class
        );

        l()->singleton(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            function () {
                return new \Illuminate\Foundation\Exceptions\Handler(l());
            }
        );

        dic()::singleton(\Illuminate\Contracts\Debug\ExceptionHandler::class, function () {
            return new \Illuminate\Foundation\Exceptions\Handler(l());
        });

        l()->singleton(
            \Illuminate\Contracts\Events\Dispatcher::class,
            function () {
                return \Octo\dispatcher();
            }
        );

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

        dic()::singleton(Factory::class, function () {
            return new SocialiteManager(l());
        });

        dic('social', l(Factory::class));

        l()->singleton(ConfigRetrieverInterface::class, function () {
            return new ConfigRetriever();
        });

        dic()::singleton(ConfigRetrieverInterface::class, function () {
            return l(ConfigRetrieverInterface::class);
        });

        dic()::singleton(SocialiteWasCalled::class, function () {
            return new SocialiteWasCalled(l(), l(ConfigRetrieverInterface::class));
        });

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

        l()->singleton('filesystem', function () {
            return new FilesystemManager(l());
        });

        l()->singleton('session.store', function ($app) {
            return $app->make('session')->driver();
        });

        l()->afterResolving('blade.compiler', function (Blader $bladeCompiler) {
        });

        l()->singleton('blade.compiler', function () {
            return bladeCompiler();
        });

        l('blade.compiler');

        makeFacade('Container', function () {
            return new Container;
        });

        makeFacade('Formy', function () {
            return \Octo\gi()->make(\App\Services\FormCrud::class, [new \Form]);
        });

        makeFacade('Redis', function () {
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

        makeFacade('View', function () {
            return view();
        });

        makeFacade('Cache', function () {
            $config = Config::get('app');

            return cacheService($config['cache_ttl'] ?? 60, 'app');
        });

        makeFacade('Files', function () {
            return l('files');
        });

        makeFacade('App', function () {
            return l();
        });

        makeFacade('Storage', function () {
            return l('filesystem');
        });

        makeFacade('Session', function () {
            return session();
        });

        makeFacade('Auth', function () {
            return trust();
        });

        makeFacade('AdminAuth', function () {
            return trust('admin');
        });

        makeFacade('Flash', function () {
            return flash();
        });

        makeFacade('Response', function () {
            return response();
        });

        makeFacade('Log', function () {
            return dic('logservice');
        });

        makeFacade('Request', function () {
            return new FastRequest;
        });

        makeFacade('Dispatcher', function () {
            return new Dispatcher(Container::getInstance(), function ($connection = null) {
                return new Queue('app', $connection);
            });
        });

        makeFacade('Bus', function () {
            return \Octo\gi()->make(\Octo\Work::class, [new \App\Services\Data('queues')]);
        });

        makeFacade('Lang', function () {
            return setTranslator(lang_path(), locale());
        });

        makeFacade('Throttle', function () {
            $cache = cacheService(60, 'throttle');

            return new Limiter($cache);
        });

        $this->social();
    }
}
