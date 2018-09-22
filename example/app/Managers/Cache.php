<?php
namespace App\Managers;

class Cache
{
    /** @var array */
    protected static $scopes = [];

    /**
     * @param string $scope
     * @param int $ttl
     * @return \App\Services\Cache
     */
    public static function get(string $scope = 'main', int $ttl = 60)
    {
        $key = sha1(serialize(func_get_args()));

        if (null === ($instance = static::$scopes[$key] ?? null)) {
            static::$scopes[$key] = $instance = cacheService($ttl, $scope);
        }

        return $instance;
    }

    /**
     * @param string $scope
     * @param int $ttl
     * @return \App\Services\Cache
     */
    public static function new(string $scope = 'main', int $ttl = 60)
    {
        return cacheService($ttl, $scope);
    }
}
