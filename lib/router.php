<?php
    namespace Octo;

    class Router
    {
        private $route, $baseRoute, $uri;
        private static $instance;

        public function __construct()
        {
            $this->baseRoute = Registry::get('octo.subdir', '');
        }

        public static function instance()
        {
            if (!static::$instance) {
                static::$instance = new static;
            }

            return static::$instance;
        }

        public function setBaseRoute($base)
        {
            $this->baseRoute = $base;

            return $this;
        }

        public function getHeaders()
        {
            if (function_exists('getallheaders')) {
                return getallheaders();
            }

            $headers = [];

            foreach ($_SERVER as $name => $value) {
                if ((substr($name, 0, 5) == 'HTTP_') || ($name == 'CONTENT_TYPE') || ($name == 'CONTENT_LENGTH')) {
                    $headers[str_replace(array(' ', 'Http'), array('-', 'HTTP'), ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }

            return $headers;
        }

        public function getMethod()
        {
            $method = $_SERVER['REQUEST_METHOD'];

            if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
                ob_start();
                $method = 'GET';
            } elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $headers = $this->getHeaders();

                if (isset($headers['X-HTTP-Method-Override']) && in_array($headers['X-HTTP-Method-Override'], ['PUT', 'DELETE', 'PATCH'])) {
                    $method = isAke($headers, 'X-HTTP-Method-Override', 'PUT');
                }
            }

            return $method;
        }

        public function getUri()
        {
            $uri = substr($_SERVER['REQUEST_URI'], strlen($this->baseRoute));

            if (strstr($uri, '?')) {
                $parts  = preg_split('/\?/', $uri, -1, PREG_SPLIT_NO_EMPTY);
                $uri    = array_shift($parts);
                $qs     = array_shift($parts);

                parse_str($qs, $output);

                foreach ($output as $k => $v) {
                    $_REQUEST[$k] = $v;
                }
            }

            $uri = trim($uri, '/');

            return !strlen($uri) ? '/' : $uri;
        }

        public function run($namespace = null, $cb404 = null)
        {
            $namespace = empty($namespace) ? __NAMESPACE__ : $namespace;

            if (empty($cb404)) {
                $cb404 = Registry::get('cb.404', null);
            }

            $method = Strings::lower($this->getMethod());

            $found = 0;
            $routes = isAke(Route::$routes, $method, []);

            if (isset(Route::$routes[$method])) {
                $found = $this->handling($routes);
            }

            if ($found < 1) {
                return $this->is404($cb404);
            } else {
                if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
                    ob_end_clean();
                }

                if (!is_array($this->route) && is_string($this->route)) {
                    view($this->route);
                }

                if (!is_array($this->route) && is_callable($this->route)) {
                    return call_user_func_array($this->route, []);
                }

                if (count($this->route) == 2) {
                    $this->route[] = true;
                }

                list($controllerName, $action, $render) = $this->route;

                $controllerFile = path('app') . DS . 'controllers' . DS . $controllerName . '.php';

                if (!is_file($controllerFile)) {
                    return $this->is404($cb404);
                }

                require_once $controllerFile;

                $class      = '\\' . $namespace . '\\App' . ucfirst(Inflector::lower($controllerName)) . 'Controller';

                $actions    = get_class_methods($class);
                $father     = get_parent_class($class);

                if ($father == 'Octo\FrontController' || $father == 'Octo\ControllerBase') {
                    $a      = $action;
                    $method = $this->getMethod();

                    $action = Strings::lower($method) . ucfirst(
                        Strings::camelize(
                            strtolower($action)
                        )
                    );

                    $controller         = new $class;
                    $controller->_name  = $controllerName;
                    $controller->action = $a;

                    Registry::set('app.controller', $controller);

                    if (in_array('bootstrap', $actions)) {
                        $controller->bootstrap();
                    } else {
                        if (in_array('init', $actions)) {
                            $controller->init();
                        }
                    }

                    if (in_array('before', $actions)) {
                        $controller->before();
                    }

                    if (in_array($action, $actions)) {
                        $controller->$action();
                    } else {
                        return $this->is404($cb404);
                    }

                    if (in_array('after', $actions)) {
                        $controller->after();
                    }

                    if (in_array('unboot', $actions)) {
                        $controller->unboot();
                    }
                } else {
                    $controller = new $class($action);
                }

                if (true === $render) {
                    self::render($controller, Registry::get('cb.404', $cb404));
                }
            }
        }

        public static function render($controller, $is404 = false)
        {
            if (!headers_sent()) {
                header("HTTP/1.1 200 OK");
            }

            ob_start();

            $content = self::html($controller);

            eval(' ?>' . $content . '<?php ');

            $html = ob_get_contents();

            ob_end_clean();

            die($html . "\n" . '<!-- generated with Octo Framework in ' . Timer::get() . ' s. -->');
        }

        private static function html($controller)
        {
            $tpl = path('app') . DS . 'views' . DS . $controller->_name . DS . $controller->action . '.phtml';

            if (File::exists($tpl)) {
                $content = File::read($tpl);

                $layout = cut('<layout>', '</layout>', $content);

                if (!empty($layout)) {
                    $content = Arrays::last(explode('</layout>', $content));
                    $layout = path('app') . DS . 'views' . DS . 'layouts' . DS . $layout . '.phtml';

                    if (File::exists($layout)) {
                        $layout = File::read($layout);

                        $content = str_replace('<content></content>', $content, $layout);
                    }
                }

                $content = self::compile($content);

                $content = str_replace(
                    '$this->partial(\'',
                    '\\Octo\\Router::partial($controller, \'' . path('app') . DS . 'views' . DS . 'partials' . DS,
                    $content
                );


                $content = str_replace(
                    '$this->',
                    '$controller->',
                    $content
                );

                $content = str_replace(['{{', '}}'], ['<?php $controller->e("', '");?>'], $content);
                $content = str_replace(['[[', ']]'], ['<?php $controller->trad("', '");?>'], $content);

                ob_start();

                eval(' namespace Octo; ?>' . $content . '<?php ');

                $html = ob_get_contents();

                ob_end_clean();

                return $html;
            } else {
                return '<h1>Error 404</h1>';
            }
        }

        public static function compile($content)
        {
            $rows = explode('<partial file=', $content);
            array_shift($rows);

            foreach ($rows as $row) {
                $file = cut('"', '"', $row);
                $content = str_replace('<partial file="' . $file . '">', '<?php $this->partial(\'' . str_replace('.', DS, $file) . '.phtml\'); ?>', $content);
            }

            return $content;
        }

        public static function partial($controller, $partial, $args = [])
        {
            if (File::exists($partial)) {
                $content = File::read($partial);

                $content = self::compile($content);

                $content = str_replace(
                    '$this->partial(\'',
                    '\\Octo\\Router::partial($controller, \'' . path('app') . DS . 'views' . DS . 'partials' . DS,
                    $content
                );

                $content = str_replace(
                    '$this->',
                    '$controller->',
                    $content
                );

                if (!empty($args)) {
                    foreach ($args as $k => $v) {
                        $controller->$k = $v;
                    }
                }

                $content = str_replace(['{{', '}}'], ['<?php $controller->e("', '");?>'], $content);
                $content = str_replace(['[[', ']]'], ['<?php $controller->trad("', '");?>'], $content);

                $tab        = explode(DS, $partial);
                $last       = str_replace('.phtml', '', array_pop($tab));
                $beforeLast = array_pop($tab);
                $partialKey = "$beforeLast.$last";

                eval(' namespace Octo; ?>' . $content . '<?php ');
            } else {
                echo '';
            }
        }

        private function is404($cb404)
        {
            if (isset($cb404) && is_callable($cb404)) {
                Registry::set('page404', true);

                call_user_func($cb404);dd('ici');
                exit;
            } else {
                header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');

                die('<h1>Error 404</h1>' . "\n" . '<!-- generated with Octo Framework in ' . Timer::get() . ' s. -->');
            }
        }

        public function handling($routes, $quit = true)
        {
            $found = 0;

            $uri = $this->getUri();

            foreach ($routes as $route) {
                if (preg_match_all('#^' . trim($route['uri']) . '$#', $uri, $matches, PREG_OFFSET_CAPTURE)) {
                    $matches = array_slice($matches, 1);

                    $params = array_map(function ($match, $index) use ($matches) {
                        if (isset($matches[$index + 1]) && isset($matches[$index + 1][0]) && is_array($matches[$index + 1][0])) {
                            return trim(substr($match[0][0], 0, $matches[$index + 1][0][1] - $match[0][1]), '/');
                        } else {
                            return isset($match[0][0]) ? trim($match[0][0], '/') : null;
                        }
                    }, $matches, array_keys($matches));

                    $this->uri = Route::getUri($route['uri'], $route['callback']);

                    if ($middleware = $this->uri->getMiddleware()) {
                        Route::filter($middleware, $this->uri);
                    }

                    if ($quit) {
                        if (is_callable($route['callback'])) {
                            $this->route = call_user_func_array($route['callback'], $params);
                        } else {
                            $this->route = explode('@', $route['callback']);
                        }
                    } else {
                        call_user_func_array($route['callback'], $params);
                    }

                    $found++;

                    if ($quit) {
                        break;
                    }
                }
            }

            return $found;
        }
    }
