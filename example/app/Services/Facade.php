<?php
namespace App\Services;

use Octo\Arrays;

class Facade
{
    /**
     * @return null|object
     */
    public static function __concern()
    {
        $original = Arrays::last(explode('\\', get_called_class()));

        return dic('facades.' . $original);
    }

    /**
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters)
    {
        return static::__concern()->{$method}(...$parameters);
    }

    /**
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return static::__concern()->{$method}(...$parameters);
    }
}
