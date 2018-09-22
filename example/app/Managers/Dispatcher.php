<?php
namespace App\Managers;

class Dispatcher
{
    protected static $scopes = [];

    /**
     * @param string $scope
     * @return \Illuminate\Events\Dispatcher
     */
    public static function laravel(string $scope = 'main')
    {
        if (null === ($instance = static::$scopes[$scope] ?? null)) {
            static::$scopes[$scope] = $instance = \Octo\dispatcher($scope);
        }

        return $instance;
    }

    /**
     * @param string $scope
     * @return \Octo\Fire
     */
    public static function get(string $scope = 'main')
    {
        if (null === ($instance = static::$scopes[$scope] ?? null)) {
            static::$scopes[$scope] = $instance = dispatcher($scope);
        }

        return $instance;
    }
}

