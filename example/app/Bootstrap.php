<?php
namespace App;

use App\Providers\Paths;
use Octo\Configurator;
use Octo\File;
use function Octo\config_path;
use function Octo\gi;
use function Octo\storage_path;
use function Octo\systemBoot;

class Bootstrap
{
    /** @var bool */
    private static $cli;

    /**
     * @param bool $cli
     * @throws \Octo\Exception
     * @throws \ReflectionException
     */
    public static function run(bool $cli = false)
    {
        static::$cli = $cli;

        systemBoot(realpath(__DIR__ . '/../'));

        static::config();
        static::providers();
    }

    /**
     * @throws \Octo\Exception
     */
    private static function config()
    {
        (new Paths)->handler();
        addConfig('app');
        addConfig('session');
        addConfig('db');
        addConfig('redis');
        addConfig('mail');

        l('config', app(Configurator::class));

        $sessions = include config_path('session.php');
        l('config')->set(['session' => $sessions]);

        $search = include config_path('search.php');
        l('config')->set(['search' => $search]);

        if (!is_dir(storage_path('search/default'))) {
            File::mkdir(storage_path('search/default'));
        }
    }

    /**
     * @param bool $cli
     * @throws \ReflectionException
     */
    private static function providers()
    {
        $providers = include config_path('providers.php');

        foreach ($providers as $provider) {
            $instance = gi()->make($provider);
            gi()->call($instance, 'handler', static::$cli);
        }
    }
}