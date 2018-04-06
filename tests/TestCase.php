<?php
use function Octo\instanciator;

abstract class TestCase extends Octo\TestCase
{
    protected $baseUrl = 'http://localhost';

    /**
     * @throws ReflectionException
     */
    public function setUp()
    {
        parent::setUp();

        date_default_timezone_set('Europe/Paris');

        Octo\Dir::rmdir(__DIR__ . '/cache');
        Octo\Dir::mkdir(__DIR__ . '/cache');
        Octo\Dir::rmdir(__DIR__ . '/storage/cache');
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

        Octo\Db::listen(
            function ($q) {
                if (!fnmatch('*caching*', $q->sql)) {
                    Octo\lvd($q->sql . ' ('.implode(', ', $q->bindings).') ['.$q->time.']');
                }
            }
        );
    }

    /**
     * @return \Octo\Instanciator
     */
    public function making()
    {
        return instanciator();
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
