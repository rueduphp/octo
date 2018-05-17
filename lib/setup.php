<?php

namespace Octo;

class Setup
{
    protected static $onStart = [];
    protected static $onDemand = [];
    protected static $middlewares = [];
    protected static $aliases = [];

    /**
     * @param $class
     * @return Setup
     */
    public static function middleware($class)
    {
        static::$middlewares[] = $class;

        return new static;
    }

    /**
     * @param string $class
     * @return Setup
     * @throws \ReflectionException
     */
    public static function register(string $class)
    {
        if (class_exists($class)) {
            if (!$i = In::self()[$class]) {
                $instance = gi()->make($class);

                if (isset($instance->onStart) && true === $instance->onStart) {
                    static::$onStart[] = $instance;
                } else {
                    static::$onDemand[] = $instance;
                }
            }
        }

        return new static;
    }

    /**
     * @throws \ReflectionException
     */
    public static function run()
    {
        static::bootstrap();
        static::later();
    }

    /**
     * @throws \ReflectionException
     */
    protected static function bootstrap()
    {
        foreach (static::$onStart as $instance) {
            gi()->call($instance, 'bootstrap');
            gi()->call($instance, 'register');
        }
    }

    /**
     * @throws \ReflectionException
     */
    protected static function later()
    {
        foreach (static::$onDemand as $class => $instance) {
            gi()->call($instance, 'register');
        }
    }

    /**
     * @return array
     */
    public static function getMiddlewares(): array
    {
        return self::$middlewares;
    }

    /**
     * @return Fillable
     * @throws \ReflectionException
     */
    public static function config(): Fillable
    {
        return gi()->make(Fillable::class, ['config']);
    }

    public static function alias(...$args)
    {
        $alias = array_shift($args);

        if (is_array($alias)) {
            static::$aliases = array_merge(static::$aliases, $alias);
        } else {
            $target = array_shift($args);

            if (null === $target) {
                $target = isAke(static::$aliases, $alias, null);

                return $target;
            }

            static::$aliases[$alias] = $target;
        }
    }

    /**
     * @param Ultimate $session
     * @return Component
     */
    public static function auth(Ultimate $session)
    {
        $auth = new Component;

        $auth['session'] = function () use ($session) {
            return $session;
        };

        $auth['user'] = function (?string $key = null, $default = null) use ($session) {
            return $session->user($key, $default);
        };

        $auth['is'] = function (string $role) use ($session) {
            /** @var array $roles */
            $roles = $session->user('roles', []);
            $has = isAke($roles, $role, null);

            return is_null($has) ? false : true;
        };

        $auth['guest'] = function () use ($session) {
            return $session->guest();
        };

        $auth['logged'] = function () use ($session) {
            return $session->logged();
        };

        $auth['can'] = function (...$args) use ($session) {
            $permission = array_shift($args) . '.' . $session->getNamespace();
            $user       = $session->user();
            $parameters = array_merge([$permission, $user], $args);

            return static::rights()->allows(...$parameters);
        };

        $auth['cannot'] = function (...$args) use ($session) {
            $permission = array_shift($args) . '.' . $session->getNamespace();
            $user       = $session->user();
            $parameters = array_merge([$permission, $user], $args);

            return static::rights()->denies(...$parameters);
        };

        $auth['policy'] = function (string $name, $callback) use ($auth, $session) {
            Facades\Rights::add($name . '.' . $session->getNamespace(), $callback);

            return $auth;
        };

        $auth['login'] = function (?callable $provider = null) use ($auth, $session) {
            if (is_callable($provider)) {
                return $auth->provider('login', $provider);
            }

            $callback = $auth->provider('login');

            return callThat($callback, $session);
        };

        $auth['logout'] = function (?callable $provider = null) use ($auth, $session) {
            if (is_callable($provider)) {
                return $auth->provider('logout', $provider);
            }

            $callback = $auth->provider('logout');

            return callThat($callback, $session);
        };

        $auth['macro'] = function (string $type, callable $provider) use ($auth) {
            return $auth->provider($type, $provider);
        };

        $auth['call'] = function (...$args) use ($auth, $session) {
            $type = array_shift($args);

            $callback = $auth->provider($type);

            $parameters = array_merge([$callback, $session], $args);

            return callThat(...$parameters);
        };

        $auth['provider'] = function (string $type, ?callable $provider = null) use ($auth, $session) {
            $providers = getCore('auth.providers.' . $session->getNamespace(), []);

            if (is_callable($provider)) {
                $providers[$type] = $provider;
                setCore('auth.providers.' . $session->getNamespace(), $providers);

                return $auth;
            }

            return isAke($providers, $type, null);
        };

        $auth['self'] = function () use ($auth) {
            return $auth;
        };

        return $auth;
    }

    /**
     * @return Component
     */
    public static function rights()
    {
        $rights = new Component;

        $rights['add'] = function (string $name, $callback) use ($rights) {
            $rules = getCore('all.rules', []);

            $rules[$name] = $callback;

            setCore('all.rules', $rules);

            return $rights;
        };

        $rights['denies'] = function (...$args) use ($rights) {
            return !$rights->allows(...$args);
        };

        $rights['allows'] = function (...$args) {
            $name = array_shift($args);

            $rule = isAke(getCore('all.rules', []), $name, null);

            if (\is_callable($rule)) {
                $params = array_merge([$rule], $args);

                return callThat(...$params);
            }

            return false;
        };

        $rights['self'] = function () use ($rights) {
            return $rights;
        };

        return $rights;
    }

    /**
     * @return Component
     */
    public static function path()
    {
        $path = new Component;

        $path['self'] = function () use ($path) {
            return $path;
        };

        $path['get'] = function (string $name, ?string $default = null) {
            return in_path($name) ?: $default;
        };

        $path['set'] = function (string $name, string $_path) use ($path) {
            in_path($name, $_path);

            return $path;
        };

        $path['add'] = function (string $name, string $_path) use ($path) {
            return $path->set($name, $_path);
        };

        $path['remove'] = function (string $name) use ($path): bool {
            $status = $path->has($name);
            unset(in_paths()[$name]);

            return $status;
        };

        $path['has'] = function (string $name): bool {
            return isset(in_paths()[$name]);
        };

        return $path;
    }

    /**
     * @return Component
     * @throws \ReflectionException
     */
    public static function route()
    {
        $router = getContainer()->router();

        $route = new Component;

        $route['self'] = function () use ($route) {
            return $route;
        };

        $route['add'] = function ($method, $path, $next, $name = null, $middleware = null) use ($router, $route) {
            $router->addRoute(Inflector::upper($method), $path, $next, $name, $middleware);

            return $route;
        };

        $route['get'] = function ($path, $next, $name = null, $middleware = null) use ($route) {
            return $route->add('get', $path, $next, $name, $middleware);
        };

        $route['post'] = function ($path, $next, $name = null, $middleware = null) use ($route) {
            return $route->add('post', $path, $next, $name, $middleware);
        };

        $route['any'] = function ($path, $next, $name = null, $middleware = null) use ($route) {
            $route->add('put', $path, $next, 'put.' . $name, $middleware);
            $route->add('delete', $path, $next, 'delete.' . $name, $middleware);

            return $route->getPost($path, $next, $name, $middleware);
        };

        $route['getPost'] = function ($path, $next, $name = null, $middleware = null) use ($route) {
            $route->add('get', $path, $next, 'get.' . $name, $middleware);

            return $route->add('post', $path, $next, 'post.' . $name, $middleware);
        };

        $route['put'] = function ($path, $next, $name = null, $middleware = null) use ($route) {
            return $route->add('put', $path, $next, $name, $middleware);
        };

        $route['delete'] = function ($path, $next, $name = null, $middleware = null) use ($route) {
            return $route->add('delete', $path, $next, $name, $middleware);
        };

        $route['list'] = function () {
            return getCore('allroutes', []);
        };

        return $route;
    }
}
