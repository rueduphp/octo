<?php
namespace Octo;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Builder as Builderer;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\MySqlGrammar as MySqlQueryGrammar;
use Illuminate\Database\Query\Grammars\SQLiteGrammar as SQLiteQueryGrammar;
use Illuminate\Database\Query\Grammars\PostgresGrammar as PostgresQueryGrammar;
use Illuminate\Database\Schema\Builder as Schema;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use PDO;

class Capsule
{
    /**
     * @var PDO
     */
    private $pdo;

    /**
     * @var Capsule
     */
    private static $instance;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param null|PDO $pdo
     *
     * @return Capsule
     */
    public static function instance(?PDO $pdo = null): Capsule
    {
        if ($pdo instanceof PDO) {
            static::$instance = new static($pdo);
        }

        return static::$instance;
    }

    /**
     * @return Capsule
     */
    public static function getInstance(): Capsule
    {
        return static::$instance;
    }

    /**
     * @param $class
     * @return Schema
     */
    public function make($class)
    {
        $this->model($class);

        return $this->schema();
    }

    /**
     * @param $class
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function model($class): Builderer
    {
        /** @var Elegant $model */
        $model = is_string($class) && class_exists($class) ? new $class() : $class;

        $connection = $this->grammar(new Connection($this->pdo));

        $resolver   = new ConnectionResolver(['octoconnection' => $connection]);

        $resolver->setDefaultConnection('octoconnection');

        $model->setConnectionResolver($resolver);

        $builder = new Builder($connection);

        $builder->macro('all', function () use ($builder) {
            return $builder->get();
        });

        $eloquent = new Builderer($builder);

        return $eloquent->setModel($model);
    }

    /**
     * @param PDO $pdo
     *
     * @return Capsule
     */
    public function setPdo(PDO $pdo): Capsule
    {
        $this->pdo = $pdo;

        return $this;
    }

    /**
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * @return Schema
     */
    public function schema()
    {
        $connection = new Connection($this->pdo);

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        switch ($driver) {
            case 'mysql':
                $connection->setSchemaGrammar(new MySqlGrammar());
                $connection->setQueryGrammar(new MySqlQueryGrammar());
                break;
            case 'sqlite':
                $connection->setSchemaGrammar(new SQLiteGrammar());
                $connection->setQueryGrammar(new SQLiteQueryGrammar());
                break;
            case 'postgres':
                $connection->setSchemaGrammar(new PostgresGrammar());
                $connection->setQueryGrammar(new PostgresQueryGrammar());
                break;
        }

        Schema::defaultStringLength(191);

        return $this->grammar($connection)->getSchemaBuilder();
    }

    /**
     * @param Connection $connection
     *
     * @return Connection
     */
    private function grammar(Connection $connection)
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        switch ($driver) {
            case 'mysql':
                $connection->setSchemaGrammar(new MySqlGrammar());
                $connection->setQueryGrammar(new MySqlQueryGrammar());
                break;
            case 'sqlite':
                $connection->setSchemaGrammar(new SQLiteGrammar());
                $connection->setQueryGrammar(new SQLiteQueryGrammar());
                break;
            case 'postgres':
                $connection->setSchemaGrammar(new PostgresGrammar());
                $connection->setQueryGrammar(new PostgresQueryGrammar());
                break;
        }

        return $connection;
    }
}
