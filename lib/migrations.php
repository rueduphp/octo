<?php
namespace Octo;

use Illuminate\Database\Schema\Builder;
use PDO;
use TypeError;

class Migrations
{
    use FastTrait;

    /**
     * @var Fast
     */
    private static $app;

    /**
     * @var PDO
     */
    private static $pdo;

    /**
     * @var Orm
     */
    private static $orm;

    /**
     * @var Migrations
     */
    private static $instance;

    /**
     * @var string
     */
    protected $name;

    public static function make(PDO $pdo = null, Fast $app = null)
    {
        if (is_null(self::$instance)) {
            self::$instance = new self;

            $app = !$app instanceof Fast ? self::$instance->getContainer() : $app;

            if (!$app instanceof Fast) {
                throw new TypeError();
            }

            self::$app = $app;

            $pdo = !$pdo instanceof PDO ? $app->resolve(PDO::class) : $pdo;

            if (!$pdo instanceof PDO) {
                $host       = getenv('MYSQL_HOST');
                $port       = getenv('MYSQL_PORT');
                $database   = getenv('MYSQL_DATABASE');
                $password   = getenv('MYSQL_ROOT_PASSWORD');

                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ];

                static::$pdo = new PDO(
                    "mysql:host=$host;port=$port;dbname=" .
                    $database,
                    'root',
                    $password,
                    $options
                );
            } else {
                static::$pdo = $pdo;
            }

            static::$orm = new Orm(self::$pdo);
        }
    }

    /**
     * @return Fast
     */
    public static function getApp()
    {
        return self::$app;
    }

    /**
     * @return PDO
     */
    public static function getPdo()
    {
        return self::$pdo;
    }

    /**
     * @return Orm
     */
    public static function getOrm()
    {
        return self::$orm;
    }

    /**
     * @return Builder
     */
    public static function getSchema()
    {
        return self::$orm->schema();
    }

    /**
     * @return Builder
     */
    public function schema()
    {
        return self::$orm->schema();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}