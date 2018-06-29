<?php
namespace App\Providers;

use App\Facades\Container;
use App\Middlewares\Exception;
use App\Middlewares\Gate;
use App\Middlewares\Session;
use App\Modules\SocialLoginModule;
use App\Modules\StaticModule;
use Illuminate\Validation\DatabasePresenceVerifier;
use Octo\Facades\Validator;
use Octo\Fast;
use Octo\Fastmiddlewarecsrf;
use Octo\Fastmiddlewaredispatch;
use Octo\Fastmiddlewarenotfound;
use Octo\Fastmiddlewarerouter;
use Octo\Fastmiddlewaretrailingslash;
use function Octo\inners;
use function Octo\innersSession;
use function Octo\startSession;

class App
{
    /**
     * @param Fast $app
     * @throws \ReflectionException
     */
    private function modules(Fast $app)
    {
        $app->addModule(StaticModule::class);
        $app->addModule(SocialLoginModule::class);
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

        $response = $app->run();

        $app->render($response);
    }

    /**
     * @param bool $cli
     * @throws \Octo\Exception
     * @throws \ReflectionException
     */
    public function handler(bool $cli)
    {
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

        if (false === $cli) {
            $this->start();
        }
    }
}
