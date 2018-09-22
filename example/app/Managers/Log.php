<?php
namespace App\Managers;

use App\Services\Log as LogService;
use Monolog\Logger as Monolog;

class Log
{
    /** @var array */
    protected static $scopes = [];

    /**
     * @param string $scope
     * @return LogService
     */
    public static function get(string $scope = 'main')
    {
        if (null === ($instance = static::$scopes[$scope] ?? null)) {
            $instance = new LogService(new Monolog($scope));
            $instance->useDailyFiles(\Octo\log_path($scope . '.log'), 5);

            static::$scopes[$scope] = $instance;
        }

        return $instance;
    }

    /**
     * @param string $scope
     * @return LogService
     */
    public static function new(string $scope = 'main')
    {
        $instance = new LogService(new Monolog($scope));

        $instance->useDailyFiles(\Octo\log_path($scope . '.log'), 5);

        return $instance;
    }
}
