<?php
namespace App\Managers;

use App\Models\User;
use App\Services\Auth as AuthService;

class Auth
{
    protected static $scopes = [];

    /**
     * @param string $scope
     * @param string $userKey
     * @param string $userModel
     * @return AuthService
     */
    public static function get(
        string $scope = 'main',
        string $userKey = 'user',
        string $userModel = User::class
    ) {
        $key = sha1(serialize(func_get_args()));

        if (null === ($instance = static::$scopes[$key] ?? null)) {
            static::$scopes[$key] = $instance = AuthService::getInstance($scope, $userKey, $userModel);
        }

        return $instance;
    }
}
