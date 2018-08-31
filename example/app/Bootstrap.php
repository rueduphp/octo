<?php
namespace App;

use App\Facades\Event;
use App\Providers\Event as EventProvider;
use App\Providers\Paths;
use App\Services\App;
use Octo\Configurator;
use Octo\File;
use function Octo\aget;
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
        static::events();
    }

    private static function events()
    {
        foreach (EventProvider::subscribers() as $subscriber) {
            Event::subscribe($subscriber);
        }
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


        $app = new App(realpath(__DIR__));
        inInstance($app, 'larapp');

        spl_autoload_register([new static, 'loader']);

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
            cf([gi()->make($provider), 'handler'], static::$cli);
        }
    }

    /**
     * @param $class
     * @throws \ReflectionException
     */
    public function loader($class)
    {
        static $facades = null;

        if (null === $facades) {
            $facades = include config_path('facades.php');
        }

        if (!class_exists($class)) {
            if (null !== ($target = aget($facades, $class, null))) {
                if (is_string($target) && class_exists($target)) {
                    class_alias($target, $class);
                } elseif (is_callable($target)) {
                    $target = get_class(call_func($target));

                    if (class_exists($target)) {
                        class_alias($target, $class);
                    }
                }
            }
        }
    }
}
