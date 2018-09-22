<?php
namespace App\Managers;

use Illuminate\Database\Connection;
use Octo\Facades\Config;
use Octo\Orm;
use PDO;

class Db
{
    /** @var array */
    protected static $scopes = [];

    /**
     * @param null|string $scope
     * @param array $config
     * @return Orm
     */
    public static function orm(?string $scope = null, array $config = [])
    {
        $db = Config::get('db') ?? [];

        if (null === $scope) {
            $scope = $db['default'];
        }

        $key = sha1(serialize(['orm', $scope, $config]));

        if (null === ($instance = static::$scopes[$key] ?? null)) {
            $conf = $db[$scope] ?? [] + $config;

            $PDOoptions = [
                PDO::ATTR_CASE                 => PDO::CASE_NATURAL,
                PDO::ATTR_ERRMODE              => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_ORACLE_NULLS         => PDO::NULL_NATURAL,
                PDO::ATTR_EMULATE_PREPARES     => false,
                PDO::ATTR_STRINGIFY_FETCHES    => false,
                PDO::ATTR_DEFAULT_FETCH_MODE   => PDO::FETCH_ASSOC,
            ];

            $pdo = new PDO(
                "{$conf['driver']}:host={$conf['host']};dbname={$conf['database']}",
                $conf['username'],
                $conf['password'],
                $PDOoptions
            );

            static::$scopes[$key] = $instance = new Orm($pdo);
        }

        return $instance;
    }


    /**
     * @param null|string $scope
     * @param array $config
     * @return Orm
     */
    public static function mysql(?string $scope = null, array $config = [])
    {
        $db = Config::get('db') ?? [];

        if (null === $scope) {
            $scope = $db['default'];
        }

        $key = sha1(serialize(['mysql', $scope, $config]));

        if (null === ($instance = static::$scopes[$key] ?? null)) {
            $conf = $db[$scope] ?? [] + $config;

            static::$scopes[$key] = $instance = new \mysqli(
                $conf['host'],
                $conf['username'],
                $conf['password'],
                $conf['database']
            );
        }

        return $instance;
    }

    /**
     * @param string $scope
     * @return Connection
     */
    public static function get(string $scope = 'mysql')
    {
        if (null === ($instance = static::$scopes[$scope] ?? null)) {
            static::$scopes[$scope] = $instance = dic('db')->connection($scope);
        }

        return $instance;
    }
}
