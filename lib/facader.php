<?php
namespace Octo;

use Closure;

class Facader
{
    public static function __callStatic(string $name, array $arguments)
    {
        $class = get_called_class();

        $methods = Registry::get("facader." . $class, []);

        $method = isAke($methods, $name, false);

        if (is_callable($method)) {
            if (is_array($method)) {
                $params = array_merge($method, $arguments);

                return instanciator()->call(...$params);
            } elseif ($method instanceof Closure) {
                $arg = current($arguments);
                $continue = true;

                if ($arg instanceof Closure && true === sameClosures($method, $arg)) {
                    $continue = false;
                }

                if (true === $continue) {
                    $params = array_merge([$method], $arguments);

                    return instanciator()->makeClosure(...$params);
                }
            }
        } else {
            $methods[$name] = current($arguments);
            Registry::set("facader." . $class, $methods);
        }
    }

    public static function __define()
    {
        $class      = get_called_class();
        $arguments  = func_get_args();
        $methods    = Registry::get("facader." . $class, []);
        $method     = array_shift($arguments);

        $methods[$method] = current($arguments);

        Registry::set("facader." . $class, $methods);
    }
}