<?php
namespace Octo;

class Db
{
    /**
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     *
     * @throws \ReflectionException
     */
    public static function __callStatic(string $name, array $arguments)
    {
        if ('sql' === $name) {
            $name = 'select';
        }

        return Capsule::connection()->{$name}(...$arguments);
    }
}