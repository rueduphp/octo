<?php
namespace App\Managers;

class Session
{
    protected static $scopes = [];

    /**
     * @param string $scope
     * @param string $userKey
     * @param string $userModel
     * @return \Octo\Ultimate
     */
    public static function get(
        string $scope = 'web',
        string $userKey = 'user',
        string $userModel = '\\App\\Models\\User'
    ) {
        $key = sha1(serialize(func_get_args()));

        if (null === ($instance = static::$scopes[$key] ?? null)) {
            static::$scopes[$key] = $instance = ultimate($scope, $userKey, $userModel);
        }

        return $instance;
    }

    /**
     * @param string $scope
     * @param string $userKey
     * @param string $userModel
     * @return \Octo\Ultimate
     */
    public static function new(
        string $scope = 'web',
        string $userKey = 'user',
        string $userModel = '\\App\\Models\\User'
    ) {
        return ultimate($scope, $userKey, $userModel);
    }
}
