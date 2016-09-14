<?php
    namespace Octo;

    use ReflectionFunction;

    class Routes
    {
        protected static $list = [];
        protected static $middlewares = [];

        public static $routes = [
            'get'       => [],
            'post'      => [],
            'put'       => [],
            'delete'    => [],
            'head'      => [],
            'patch'     => [],
            'option'    => []
        ];

        public static function __callStatic($m, $a)
        {
            $uri        = array_shift($a);
            $callback   = array_shift($a);

            $method     = Strings::lower($m);

            $methods = [];

            if ('list' == $method) {
                require_once path("app") . DS . 'config' . DS . 'routes.php';

                $collection = [];

                foreach (static::$list as $route) {
                    $row = [
                        'name'      => $route->getName(),
                        'method'    => $route->getMethod(),
                        'uri'       => $route->getUri()
                    ];

                    if ($controller = $route->getController()) {
                        $row['controller'] = $controller;
                    }

                    if ($action = $route->getAction()) {
                        $row['action'] = $action;
                    }

                    if ($group = $route->getGroup()) {
                        $row['group'] = $group;
                    }

                    if ($middleware = $route->getMiddleware()) {
                        $row['middleware'] = $middleware;
                    }

                    $collection[] = $row;
                }

                return $collection;
            } elseif ('getpost' == $method) {
                $methods[] = 'get';
                $methods[] = 'post';
            } elseif ('any' == $method) {
                $methods[] = 'get';
                $methods[] = 'post';
                $methods[] = 'put';
                $methods[] = 'delete';
                $methods[] = 'head';
                $methods[] = 'patch';
                $methods[] = 'option';
            } else {
                array_push($methods, $method);
            }

            foreach ($methods as $meth) {
                if (!isset(static::$routes[$meth])) static::$routes[$meth] = [];

                array_unshift(static::$routes[$meth], ['uri' => $uri, 'callback' => $callback]);
            }

            $reflection = reflectClosure($callback);
            $arguments  = $reflection->getParameters();

            $params = [];

            foreach ($arguments as $arg) {
                $arg        = (array) $arg;
                $key        = current(array_values($arg));
                $params[]   = $key;
            }

            $url = $name = $uri;

            foreach ($params as $param) {
                $seg    = cut('(', ')', $url);
                $url    = str_replace_first("($seg)", "##$param##", $url);
                $name   = str_replace_first("($seg)", $param, $name);
            }

            $route = model('uri', [
                'name'      => str_replace('/', '.', $name),
                'uri'       => $uri,
                'url'       => $url,
                'method'    => $method,
                'params'    => $params
            ]);

            $route->macro('as', function ($name) use ($route) {
                return $route->setName($name);
            });

            $route->macro('uses', function ($string) use ($route) {
                list($controller, $action) = explode('@', $string, 2);

                return $route
                    ->setController($controller)
                    ->setAction($action)
                    ->setName(str_replace('@', '.', $string));
            });

            static::$list[] = $route;

            return $route;
        }

        public static function getName($url, callable $callback)
        {
            $name = $url;

            $reflection = new ReflectionFunction($callback);
            $arguments  = $reflection->getParameters();

            $params = [];

            foreach ($arguments as $arg) {
                $arg        = (array) $arg;
                $key        = current(array_values($arg));
                $params[]   = $key;
            }

            foreach ($params as $param) {
                $seg    = cut('(', ')', $url);
                $url    = str_replace_first("($seg)", '', $url);
                $name   = str_replace_first("($seg)", $param, $name);
            }

            return str_replace('/', '.', $name);
        }

        public static function getUri($url, callable $callback)
        {
            return static::findByName(static::getName($url, $callback));
        }

        public static function findByName($value)
        {
            return static::find('name', $value);
        }

        public static function findByUri($value)
        {
            return static::find('uri', $value);
        }

        public static function find($field, $value)
        {
            $getter = getter($field);

            foreach (static::$list as $route) {
                if ($value == $route->$getter()) {
                    return $route;
                }
            }

            return null;
        }

        public static function url($name, $args = [])
        {
            $url = null;

            $route = static::findByName($name);

            if ($route) {
                $url = $route->getUrl();

                foreach ($args as $k => $v) {
                    $url = str_replace("##$k##", $v, $url);
                }
            }

            return $url;
        }

        public static function middleware($name, callable $cb)
        {
            static::$middlewares[$name] = $cb;
        }

        public static function filter($middleware, Uri $uri)
        {
            $cb = isAke(static::$middlewares, $middleware, null);

            if (is_callable($cb)) {
                return call($cb, [$uri]);
            }
        }

        public static function group($name, $middleware, array $routes)
        {
            foreach ($routes as $route) {
                $route->setGroup($name)->setMiddleware($middleware);
            }
        }
    }
