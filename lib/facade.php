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
        $instance = instanciator()->singleton(self::getNativeClass());

        if (!$instance) {
            throw new RuntimeException('Facade has not been set.');
        }

        return $instance->$method(...$args);
    }

    /**
     * @return string
     */
    private static function getNativeClass(): string
    {
        throw new RuntimeException('Facade does not implement getNativeClass method.');
    }
}