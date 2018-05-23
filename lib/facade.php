<?php
namespace Octo;

use RuntimeException;

class Facade
{
    /**
     * @param string $method
     * @param array $args
     * @return mixed|null
     * @throws \ReflectionException
     */
    public static function __callStatic(string $method, array $args)
    {
        try {
            $class = static::getNativeClass();

            if (!$instance = in($class)) {
                if (class_exists($class)) {
                    $instance = gi()->make($class);
                    in($class, $instance);
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
