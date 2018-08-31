<?php
namespace App\Providers;

use App\Facades\Container;
use App\Facades\Request;
use App\Middlewares\Exception;
use App\Middlewares\Gate;
use App\Middlewares\Session;
use App\Modules\CrudModule;
use App\Modules\SocialLoginModule;
use App\Modules\StaticModule;
use App\Modules\UserModule;
use App\Services\Loader;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Facades\Facade;
use Illuminate\Validation\DatabasePresenceVerifier;
use Octo\Facades\Validator;
use Octo\Fast;
use Octo\Fastmiddlewarecsrf;
use Octo\Fastmiddlewaredispatch;
use Octo\Fastmiddlewarenotfound;
use Octo\Fastmiddlewarerouter;
use Octo\Fastmiddlewaretrailingslash;
use function Octo\config_path;
use function Octo\inners;
use function Octo\innersSession;
use function Octo\startSession;
use Psr\Http\Message\ResponseInterface;

class App
{
    /**
     * @throws \ReflectionException
     */
    protected function boot()
    {
        $aliases = include config_path('aliases.php');
        Loader::getInstance($aliases)->register();

        \Config::set('search.connections.algolia.config.application_id', pkey('algolia.application_id'));
        \Config::set('search.connections.algolia.config.admin_api_key', pkey('algolia.admin_api_key'));

        $storage = include config_path('storage.php');
        l('config')->set('filesystems', $storage);

        makeFacade('Disk', function () {
            return \Storage::disk('local');
        });

        Facade::setFacadeApplication(l());
    }

    /**
     * @param Fast $app
     */
    protected function booted(Fast $app)
    {

    }

    /**
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    protected function after(ResponseInterface $response)
    {
        return $response;
    }

    /**
     * @param Fast $app
     * @throws \ReflectionException
     */
    private function modules(Fast $app)
    {
        $app->addModule(StaticModule::class);
        $app->addModule(SocialLoginModule::class);

        if (Request::contains('crud')) {
            $app->addModule(CrudModule::class);
        }

        AbstractPaginator::viewFactoryResolver(function () {
            return view();
        });

        AbstractPaginator::currentPageResolver(function () {
            return Request::get('page');
        });
    }

    /**
     * @param Fast $app
     */
    private function middlewares(Fast $app)
    {
        $app
            ->addMiddleware(Exception::class)
            ->addMiddleware(Fastmiddlewaretrailingslash::class)
            ->addMiddleware(Fastmiddlewarecsrf::class)
            ->addMiddleware(Session::class)
            ->addMiddleware(Fastmiddlewarerouter::class)
            ->addMiddleware(Gate::class)
            ->addMiddleware(Fastmiddlewaredispatch::class)
            ->addMiddleware(Fastmiddlewarenotfound::class)
        ;
    }

    /**
     * @throws \ReflectionException
     */
    private function start()
    {
        Container::init();

        $app = \Octo\App::create();

        $this->middlewares($app);
        $this->modules($app);

        $this->booted($app);

        $response = $app->run();

        $response = $this->after($response);

        $app->render($response);
    }

    /**
     * @param bool $cli
     * @throws \Octo\Exception
     * @throws \ReflectionException
     */
    public function handler($cli)
    {
        if (!is_bool($cli)) {
            $cli = false;
        }

        inners();

        if (false === $cli) {
            $sessionRedis = new \App\Services\SessionRedis;
            $sessionRedis->setRedis(l('redis'));
            session_set_save_handler($sessionRedis);
            startSession();
        }

        innersSession();

        (new Facades)->handler();

        directives();

        $verifier = new DatabasePresenceVerifier(l('db'));

        Validator::setPresenceVerifier($verifier);

        $this->boot();

        if (false === $cli) {
            $this->start();
        }
    }
}
