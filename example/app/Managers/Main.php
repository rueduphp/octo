<?php
namespace App\Managers;

use App\Models\User;
use App\Services\Auth as AuthService;
use App\Services\Cache as CacheService;
use App\Services\Log as LogService;
use App\Services\Container;
use Octo\Component;
use Octo\Fast;
use Octo\FastRequest;
use Octo\Fire;
use Octo\Listener;
use Octo\Ultimate as SessionService;

class Main
{
    /** @var Main */
    protected static $__instance;

    /** @var array */
    protected static $__components = [];

    /** @var Container */
    protected $container;

    public function __construct()
    {
        $this->container = Container::getInstance();
        $this->container['main.app'] = App::getInstance();
        $this->container['main.dispatcher'] = Dispatcher::get();
        $this->container['main.request'] = new FastRequest;

        $this->container['kernel'] = function () {
            return \Octo\fast();
        };

        include __DIR__ . '/../config/main.php';
    }

    /**
     * @return Main
     */
    public static function getInstance(): self
    {
        if (null === static::$__instance) {
            static::$__instance = new static;
        }

        return static::$__instance;
    }

    /**
     * @param string $name
     * @param array $data
     * @return Component
     */
    public function component(string $name, array $data = []): Component
    {
        if (null === ($component = static::$__components[$name] ?? null)) {
            $component = new Component($data);

            static::$__components[$name] = $component;
        }

        return $component;
    }

    /**
     * @param string $scope
     * @param int $ttl
     * @return CacheService
     */
    public function cache(string $scope = 'main', int $ttl = 60): CacheService
    {
        return Cache::get($scope, $ttl);
    }

    /**
     * @param string $scope
     * @return \Illuminate\Database\Connection
     */
    public function db(string $scope = 'mysql')
    {
        return Db::get($scope);
    }

    /**
     * @param null|string $scope
     * @param array $config
     * @return \Octo\Orm
     */
    public function orm(?string $scope = null, array $config = [])
    {
        return Db::orm($scope, $config);
    }

    /**
     * @param string $scope
     * @return LogService
     */
    public function log(string $scope = 'main'): LogService
    {
        return Log::get($scope);
    }

    /**
     * @param string $scope
     * @param string $userKey
     * @param string $userModel
     * @return AuthService
     */
    public function auth(
        string $scope = 'main',
        string $userKey = 'user',
        string $userModel = User::class
    ): AuthService {
        return Auth::get($scope, $userKey, $userModel);
    }

    /**
     * @param string $scope
     * @param string $userKey
     * @param string $userModel
     * @return SessionService
     */
    public function session(
        string $scope = 'main',
        string $userKey = 'user',
        string $userModel = User::class
    ): SessionService {
        return Session::get($scope, $userKey, $userModel);
    }

    /**
     * @param string $scope
     * @return string
     */
    public function locale(string $scope = 'main'): string
    {
        return locale($this->session($scope));
    }

    /**
     * @param string $locale
     * @param string $scope
     * @return Main
     */
    public function setLocale(string $locale, string $scope = 'main'): self
    {
        $session = $this->session($scope);
        $session[$session->getLocaleKey()] = $locale;

        return $this;
    }

    /**
     * @param string $scope
     * @return Main
     */
    public function resetLocale(string $scope = 'main'): self
    {
        $session = $this->session($scope);

        unset($session[$session->getLocaleKey()]);

        return $this;
    }

    /**
     * @param string $scope
     * @return Component
     */
    public function flash(string $scope = 'main')
    {
        $key = 'flash.' . $scope;

        if (null === ($flash = $this->app[$key] ?? null)) {
            $flash =  flasher($this->session($key));
            $this->app[$key] = $flash;
        }

        return $flash;
    }

    /**
     * @param array $data
     * @param string $scope
     * @return Main
     */
    public function with(array $data, string $scope = 'view'): self
    {
        $this->session($scope)->set('_with', $data);

        return $this;
    }

    /**
     * @param null|string $name
     * @param array $data
     * @param array $mergeData
     * @param string $scope
     * @return \Illuminate\View\Factory|string
     */
    public function view(?string $name = null, array $data = [], array $mergeData = [], string $scope = 'view')
    {
        /** @var \Illuminate\View\Factory $view */
        $view = dic('view');

        if (0 === func_num_args()) {
            return $view;
        }

        $data += \Octo\viewParams()->toArray();
        $data += $this->session($scope)->pull('_with', []);

        $data['errors'] = $data['errors'] ?? coll();

        \Octo\setCore('blade.context', $data);

        return $view->make($name, $data, $mergeData)->render();
    }

    /**
     * @param string $method
     * @param array $args
     * @return mixed|null
     */
    public function __call(string $method, array $args)
    {
        if (function_exists($method)) {
            return call_func($method, ...$args);
        }

        if (function_exists('\\Octo\\' . $method)) {
            return call_func('\\Octo\\' . $method, ...$args);
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }

    /**
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public static function __callStatic(string $method, array $args)
    {
        return static::getInstance()->{$method}(...$args);
    }

    /**
     * @return App
     */
    public function app(): App
    {
        return $this->container['main.app'];
    }

    /**
     * @return Container
     */
    public function container(): Container
    {
        return $this->container;
    }

    /**
     * @return Fire
     */
    public function dispatcher(): Fire
    {
        return $this->container['main.dispatcher'];
    }

    /**
     * @param mixed ...$args
     * @return Listener
     */
    public function listen(...$args): Listener
    {
        return $this->dispatcher->on(...$args);
    }

    /**
     * @param mixed ...$args
     * @return array|mixed|null
     */
    public function fire(...$args)
    {
        return $this->dispatcher->emit(...$args);
    }

    /**
     * @return FastRequest
     */
    public function request(): FastRequest
    {
        return $this->container['main.request'];
    }

    /**
     * @return Fast
     */
    public function kernel(): Fast
    {
        return $this->container['kernel'];
    }

    /**
     * @param mixed ...$args
     * @return \App\Services\Model|mixed|null
     */
    public function user(...$args)
    {
        return $this->auth()->user(...$args);
    }
}
