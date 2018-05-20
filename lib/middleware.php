<?php
namespace Octo;

class Middleware
{
    private static $datas = [];
    private static $arguments = [];

    public function __construct()
    {
        $config = realpath(config_path() . '/middlewares.php');

        if (is_file($config) && is_readable($config)) {
            $middlewares = include $config;

            foreach ($middlewares as $name => $middleware) {
                $this->define($name, $middleware['callback'], isAke($middleware, 'args', []));
            }
        }
    }

    public function define($className, callable $closure, array $args = [])
    {
        if (!isset(static::$datas[$className])) {
            static::$datas[$className] = static::$arguments[$className] = [];
        }

        static::$datas[$className][]      = $closure;
        static::$arguments[$className][]  = $args;

        return $this;
    }

    public function listen($className)
    {
        $closures = isAke(static::$datas, $className, []);

        if (!empty($closures)) {
            $i = 0;

            foreach ($closures as $closure) {
                if (is_callable($closure)) {
                    $args = static::$arguments[$className][$i] ?? [];

                    call_user_func_array($closure, $args);
                }

                $i++;
            }
        }
    }
}
