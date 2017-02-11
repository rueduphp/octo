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

        public static function model($model, $prefix = '', $suffix = 'model')
        {
            if (is_object($model)) {
                $db     = $model->db();
                $table  = $model->table();

                if ('core' != $db) {
                    $prefix = trim(trim($orefix, '/') . '/' . $db, '/');
                }

                $prefix = trim(trim($orefix, '/') . '/' . $table, '/');

                $controller = $db . $table . $suffix;

                /*
                |~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
                | Create
                |~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
                */
                self::getPost($prefix . '/create', $controller . '#create');

                /*
                |~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
                | Read
                |~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
                */
                self::get($prefix . '/read/([0-9]+)', function ($id) use ($controller) {
                    $_REQUEST['id'] = $id;

                    return [$controller, 'read'];
                });

                /*
                |~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
                | Update
                |~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
                */
                self::getPost($prefix . '/update/([0-9]+)', function ($id) use ($controller) {
                    $_REQUEST['id'] = $id;

                    return [$controller, 'update'];
                });

                /*
                |~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
                | Delete
                |~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
                */
                self::getPost($prefix . '/delete/([0-9]+)', function ($id) use ($controller) {
                    $_REQUEST['id'] = $id;

                    return [$controller, 'delete'];
                });

                /*
                |~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
                | List
                |~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
                */
                self::getPost($prefix . '/list', $controller . '#list');
            }
        }

        public static function __callStatic($m, $a)
        {
            $uri        = array_shift($a);
            $callback   = array_shift($a);

            if (!$callback instanceof \Closure) {
                $callback = function () use ($callback) {
                    $render = null;

                    if (fnmatch('*#*', $callback)) {
                        if (fnmatch('*#*#*', $callback)) {
                            list($controller, $action, $render) = explode('#', $callback, 3);
                        } else {
                            list($controller, $action) = explode('#', $callback, 2);
                        }
                    } elseif (fnmatch('*@*', $callback)) {
                        if (fnmatch('*@*@*', $callback)) {
                            list($controller, $action, $render) = explode('@', $callback, 3);
                        } else {
                            list($controller, $action) = explode('@', $callback, 2);
                        }
                    } elseif (fnmatch('*.*', $callback)) {
                        if (fnmatch('*.*.*', $callback)) {
                            list($controller, $action, $render) = explode('.', $callback, 3);
                        } else {
                            list($controller, $action) = explode('.', $callback, 2);
                        }
                    }

                    $render = empty($render);

                    return [$controller, $action, $render];
                };
            }

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

            $name = str_replace('/', '.', $name);

            $name = '.' === $name ? 'home' : $name;

            $route = model('uri', [
                'name'      => $name,
                'uri'       => $uri,
                'url'       => $url,
                'method'    => $method,
                'params'    => $params
            ]);

            $route->macro('as', function ($name) use ($route) {
                return $route->setName($name);
            });

            $route->macro('middleware', function (callable $cb) use ($route) {
                return $route->setMiddleware($cb);
            });

            $route->macro('uses', function ($string) use ($route) {
                if (fnmatch('*@*', $string)) {
                    list($controller, $action) = explode('@', $string, 2);
                } elseif (fnmatch('*#*', $string)) {
                    list($controller, $action) = explode('#', $string, 2);
                } elseif (fnmatch('*.*', $string)) {
                    list($controller, $action) = explode('.', $string, 2);
                }

                return $route
                    ->setController($controller)
                    ->setAction($action);
            });

            if (!empty($a)) {
                $middleware = array_shift($a);
                static::$middlewares[$name] = $middleware;
            }

            static::$list[] = $route;

            return $route;
        }

        public static function getName($url, callable $callback)
        {
            $name = $url;

            $reflection = reflectClosure($callback);
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

            $name = str_replace('/', '.', $name);

            $name = '.' === $name ? 'home' : $name;

            return $name;
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

        public static function getMiddleware($name)
        {
            return isAke(static::$middlewares, $name, null);
        }

        public static function filter($middleware, Uri $uri)
        {
            $cb = isAke(static::$middlewares, $middleware, null);

            if (is_callable($cb)) {
                return call($cb, [$uri]);
            }

            return null;
        }

        public static function group($middleware, array $routes)
        {
            foreach ($routes as $route) {
                static::$middlewares[$route->getName()] = $middleware;
            }
        }
    }
