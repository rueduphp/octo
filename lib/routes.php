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

        public static function resource($model, $controller = null)
        {
            if (is_object($model)) {
                $db     = $model->db();
                $table  = $model->table();

                $prefix = '';

                if ('core' != $db) {
                    $prefix = trim(trim($orefix, '/') . '/' . $db, '/');
                }

                $controller = empty($controller) ? $table : $controller;

                $prefix = trim(trim($orefix, '/') . '/' . $controller, '/');

                /*
                |~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
                | Create
                |~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
                */
                self::get($prefix . '/add', $controller . '#add');
                self::post($prefix . '/store', $controller . '#store');

                /*
                |~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
                | Read
                |~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
                */
                self::get($prefix . '/([0-9]+)', function ($id) use ($controller) {
                    $_REQUEST['id'] = $id;

                    return [$controller, 'find'];
                });

                /*
                |~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
                | Update
                |~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
                */
                self::get($prefix . '/edit/([0-9]+)', function ($id) use ($controller) {
                    $_REQUEST['id'] = $id;

                    return [$controller, 'update'];
                });

                self::post($prefix . '/update/([0-9]+)', function ($id) use ($controller) {
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
                self::getPost($prefix, $controller . '#index');
                self::getPost($prefix . '/list', $controller . '#index');
            }
        }

        public static function json(array $array)
        {
            return toClosure($array);
        }

        public static function prefix($prefix, callable $next)
        {
            Registry::set('core.routes.prefix', $prefix);

            $next();

            Registry::set('core.routes.prefix', '');
        }

        public static function before($before, callable $next)
        {
            if (is_string($before)) {
                $class = maker($before);

                $before = [$class, 'handle'];
            }

            if (is_callable($before)) {
                Registry::set('core.routes.before', $before);

                $next();

                Registry::set('core.routes.before', null);
            }
        }

        public static function __callStatic($m, $a)
        {
            if ('array' == $m) {
                return toClosure($a);
            }

            $before     = Registry::get('core.routes.before', null);
            $prefix     = Registry::get('core.routes.prefix', '');
            $prefix     = strlen($prefix) ? trim($prefix, '/') . '/' : $prefix;
            $uri        = $prefix . array_shift($a);
            $callback   = array_shift($a);

            if (!$callback instanceof \Closure) {
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

                $callback = compactCallback($controller, $action, $render);
                // $callback = function () use ($controller, $action, $render) {
                //     return [$controller, $action, $render];
                // };
            }

            $method = Strings::lower($m);

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

                $exists = coll(static::$routes[$meth])->where('uri', $uri)->count() > 0;

                if (!$exists) {
                    array_unshift(static::$routes[$meth], ['uri' => $uri, 'callback' => $callback]);
                } else {
                    exception('Routes', "The route with uri $uri ever exists for " . Strings::upper($meth) ." method.");
                }
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

            $dataRoute = [
                'name'      => $name,
                'uri'       => $uri,
                'url'       => $url,
                'method'    => $method,
                'params'    => $params
            ];

            if (isset($controller)) $dataRoute["controller"]    = $controller;
            if (isset($action))     $dataRoute["action"]        = $action;
            if (isset($render))     $dataRoute["render"]        = $render;

            $route = model('uri', $dataRoute);

            if (is_callable($before)) {
                $route->setBefore($before);
            }

            $route->macro('as', function ($name) use ($route) {
                return $route->setName($name);
            });

            $route->macro('middleware', function ($middleware) use ($route) {
                return $route->setMiddleware($middleware);
            });

            $route->macro('uses', function ($string) use ($route) {
                if (fnmatch('*@*', $string)) {
                    list($controller, $action) = explode('@', $string, 2);
                } elseif (fnmatch('*#*', $string)) {
                    list($controller, $action) = explode('#', $string, 2);
                } elseif (fnmatch('*.*', $string)) {
                    list($controller, $action) = explode('.', $string, 2);
                } elseif (fnmatch('*:*', $string)) {
                    list($controller, $action) = explode(':', $string, 2);
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

        public static function isRoute($name, $args = [])
        {
            return static::url($name, $args) == $_SERVER['REQUEST_URI'];
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

        public static function crud($entity)
        {
            static::get('admin/' . $entity . '/list', function () use ($entity) {
                $_REQUEST['entity_crud'] = $entity;

                return ['admin', 'listCrud'];
            })->as('list' . $entity);

            static::get('admin/' . $entity . '/list-(.*)', function () use ($entity) {
                $_REQUEST['entity_crud'] = $entity;

                return ['admin', 'listCrud'];
            })->as('list' . $entity . 'p');

            static::get('admin/' . $entity . '/create', function () use ($entity) {
                $_REQUEST['entity_crud'] = $entity;

                return ['admin', 'createCrud'];
            })->as('create' . $entity);

            static::get('admin/' . $entity . '/delete/([0-9]+)', function ($id) use ($entity) {
                $_REQUEST['entity_crud'] = $entity;
                $_REQUEST['id'] = $id;

                return ['admin', 'deleteCrud'];
            })->as('delete' . $entity);

            static::get('admin/' . $entity . '/edit/([0-9]+)', function ($id) use ($entity) {
                $_REQUEST['entity_crud'] = $entity;
                $_REQUEST['id'] = $id;

                return ['admin', 'editCrud'];
            })->as('edit' . $entity);

            static::post('admin/' . $entity . '/update/([0-9]+)', function ($id) use ($entity) {
                $_REQUEST['entity_crud'] = $entity;
                $_REQUEST['id'] = $id;

                return ['admin', 'updateCrud'];
            })->as('update' . $entity);

            static::post('admin/' . $entity . '/add', function () use ($entity) {
                $_REQUEST['entity_crud'] = $entity;

                return ['admin', 'addCrud'];
            })->as('add' . $entity);
        }

        public static function cruding($entity)
        {
            static::get('admin/' . $entity . '/list', function () use ($entity) {
                $_REQUEST['entity_crud'] = $entity;

                return ['admin', 'list' . $entity];
            })->as('list' . $entity);

            static::get('admin/' . $entity . '/list-(.*)', function () use ($entity) {
                $_REQUEST['entity_crud'] = $entity;

                return ['admin', 'list' . $entity];
            })->as('list' . $entity . 'p');

            static::get('admin/' . $entity . '/create', function () use ($entity) {
                $_REQUEST['entity_crud'] = $entity;

                return ['admin', 'create' . $entity];
            })->as('create' . $entity);

            static::get('admin/' . $entity . '/delete/([0-9]+)', function ($id) use ($entity) {
                $_REQUEST['entity_crud'] = $entity;
                $_REQUEST['id'] = $id;

                return ['admin', 'delete' . $entity];
            })->as('delete' . $entity);

            static::get('admin/' . $entity . '/edit/([0-9]+)', function ($id) use ($entity) {
                $_REQUEST['entity_crud'] = $entity;
                $_REQUEST['id'] = $id;

                return ['admin', 'edit' . $entity];
            })->as('edit' . $entity);

            static::post('admin/' . $entity . '/update/([0-9]+)', function ($id) use ($entity) {
                $_REQUEST['entity_crud'] = $entity;
                $_REQUEST['id'] = $id;

                return ['admin', 'update' . $entity];
            })->as('update' . $entity);

            static::post('admin/' . $entity . '/add', function () use ($entity) {
                $_REQUEST['entity_crud'] = $entity;

                return ['admin', 'add' . $entity];
            })->as('add' . $entity);
        }
    }
