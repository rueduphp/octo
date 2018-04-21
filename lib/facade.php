<?php
namespace Octo;

use RuntimeException;

class Facade
{
    /**
     * @param $method
     * @param $args
     *
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        try {
            $instance = null;

            $class = static::getNativeClass();

            if (In::has($class)) {
                $instance = In::get($class);
            } else {
                if (class_exists($class)) {
                    $instance = gi()->make($class);
                    In::setInstance($instance);
                }
            }

            if (!is_object($instance) || !$instance) {
                throw new RuntimeException(get_called_class() . ' has not been set.');
            }

            $params = array_merge([$instance, $method], $args);

            return gi()->call(...$params);
        } catch (\Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * @return string
     */
    private static function getNativeClass(): string
    {
        throw new RuntimeException(get_called_class() . ' does not implement getNativeClass method.');
    }
}
