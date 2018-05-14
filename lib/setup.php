<?php

namespace Octo;

use function func_get_args;

class Setup
{
    protected static $onStart = [];
    protected static $onDemand = [];
    protected static $middlewares = [];

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

    public static function rights()
    {
        $rights = new Component;

        $rights['add'] = function (string $name, $callback) use ($rights) {
            $rules = getCore('all.rules', []);

            $rules[$name] = $callback;

            setCore('all.rules', $rules);

            return $rights;
        };

        $rights['denies'] = function () use ($rights) {
            $args = func_get_args();

            return !$rights->allows(...$args);
        };

        $rights['allows'] = function () {
            $args = func_get_args();

            $name = \array_shift($args);

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
