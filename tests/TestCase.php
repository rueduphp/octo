<?php
use Illuminate\Filesystem\Filesystem;

abstract class TestCase extends Octo\TestCase
{
    protected $baseUrl = 'http://localhost';

    /**
     * @throws ReflectionException
     */
    public function setUp()
    {
        defined('testing') || define('testing', true);

        parent::setUp();

        date_default_timezone_set('Europe/Paris');

        Octo\Dir::mkdir(__DIR__ . '/cache');
        Octo\Dir::mkdir(__DIR__ . '/cache/views');
        Octo\Dir::mkdir(__DIR__ . '/session');
        Octo\Dir::mkdir(__DIR__ . '/log');
        Octo\Dir::mkdir(__DIR__ . '/storage/cache');

        $PDOoptions = [
            PDO::ATTR_CASE                 => PDO::CASE_NATURAL,
            PDO::ATTR_ERRMODE              => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_ORACLE_NULLS         => PDO::NULL_NATURAL,
            PDO::ATTR_DEFAULT_FETCH_MODE   => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES    => false,
            PDO::ATTR_EMULATE_PREPARES     => false
        ];

        $pdo = new PDO('sqlite::memory:', null, null, $PDOoptions);

        $db = new Octo\Orm($pdo);

        Tests\Migrations::migrate($db->schema());
        Tests\Migrations::seeds($db);

        Octo\Capsule::instance($pdo);

//        Octo\Db::listen(
//            function ($q) {
//                if (!fnmatch('*caching*', $q->sql)) {
//                    Octo\lvd($q->sql . ' ('.implode(', ', $q->bindings).') ['.$q->time.']');
//                }
//            }
//        );

        $paths = Octo\in_paths();

        $paths['app']       = __DIR__;
        $paths['base']      = __DIR__;
        $paths['cache']     = __DIR__ . '/cache';
        $paths['storage']   = __DIR__ . '/storage';
        $paths['session']   = __DIR__ . '/session';
        $paths['log']       = __DIR__ . '/log';
        $paths['lang']      = __DIR__ . '/lang';

        Octo\inners();

        $in = Octo\In::self();

        $in::singleton('filesession', function () {
            return new Octo\Nativesession(new Filesystem, Octo\session_path(), 120);
        });

        $in::singleton('instant', function () use ($in) {
            return (new Octo\Instant('core', $in['filesession']))->start();
        });
    }

    public function tearDown()
    {
        parent::tearDown();

        Octo\Dir::rmdir(__DIR__ . '/log');
        Octo\Dir::rmdir(__DIR__ . '/cache');
        Octo\Dir::rmdir(__DIR__ . '/session');
        Octo\Dir::rmdir(__DIR__ . '/storage/cache');
    }

    /**
     * @return mixed|object|Octo\Instanciator
     * @throws ReflectionException
     */
    public function making()
    {
        return Octo\gi();
    }

    /**
     * @return \Octo\Context
     */
    public function makeApplication()
    {
        Octo\Config::set('octalia.engine', 'rdb');
        Octo\Config::set('dir.cache', __DIR__ . '/cache');
        Octo\Config::set('fmr.instance', new Octo\Now('testcache'));
        Octo\Config::set('DATABASE_DRIVER', 'sqlite');

        return Octo\context('app');
    }

    public function __invoke()
    {
        return get_called_class();
    }
}
