<?php
    namespace Octo;

    class Middleware
    {
        private static $datas = [];
        private static $arguments = [];

        public function __construct()
        {
            $config = realpath(path('app') . '/config/middlewares.php');

            if (is_file($config) && is_readable($config)) {
                $middlewares = include $config;

                foreach ($middlewares as $name => $middleware) {
                    $this->define($name, $middleware['callback'], isAke($middleware, 'args', []));
                }
            }
        }

        public function define($className, callable $closure, array $args = [])
        {
            if (!isset(self::$datas[$className])) {
                self::$datas[$className] = self::$arguments[$className] = [];
            }

            self::$datas[$className][]      = $closure;
            self::$arguments[$className][]  = $args;

            return $this;
        }

        public function listen($className)
        {
            $closures = isAke(self::$datas, $className, []);

            if (!empty($closures)) {
                $i = 0;

                foreach ($closures as $closure) {
                    if (is_callable($closure)) {
                        $args = isset(self::$arguments[$className][$i]) ? self::$arguments[$className][$i] : [];

                        call_user_func_array($closure, $args);
                    }

                    $i++;
                }
            }
        }
    }
