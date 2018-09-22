<?php
namespace App\Managers;

use App\Models\User;
use App\Services\Reddy;

class Flash
{
    /** @var array */
    protected static $storage = [];

    /**
     * @param string $namespace
     * @param string $userKey
     * @param string $userModel
     * @return Reddy
     */
    public static function getInstance(
        string $namespace = 'core',
        string $userKey = 'user',
        string $userModel = User::class
    ) {
        $key = sha1(serialize(func_get_args()));

        if (null === ($instance = static::$storage[$key] ?? null)) {
            $instance = new Reddy($namespace, $userKey, $userModel);
            static::$storage[$key] = $instance;
        }

        return $instance;
    }
}
