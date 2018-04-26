<?php
namespace Octo;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Builder as Builderer;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\MySqlGrammar as MySqlQueryGrammar;
use Illuminate\Database\Query\Grammars\SQLiteGrammar as SQLiteQueryGrammar;
use Illuminate\Database\Query\Grammars\PostgresGrammar as PostgresQueryGrammar;
use Illuminate\Database\Query\Grammars\SqlServerGrammar as SqlServerQueryGrammar;
use Illuminate\Database\Schema\Builder as Schema;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Database\Schema\Grammars\SqlServerGrammar;
use PDO;

class Connector extends Elegant
{
    /**
     * @param $class
     * @param PDO $pdo
     * @return Builderer
     * @throws \ReflectionException
     */
    public static function model(Connected $model, PDO $pdo): Builderer
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $connection = static::grammar(new Connection($pdo), $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        $resolver = new ConnectionResolver([$driver => $connection]);

        $resolver->setDefaultConnection($driver);

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
     * @return Schema
     * @throws \ReflectionException
     */
    public static function schema(PDO $pdo)
    {
        $connection = new Connection($pdo);

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

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
            case 'sqlsrv':
                $connection->setSchemaGrammar(new SqlServerGrammar());
                $connection->setQueryGrammar(new SqlServerQueryGrammar());
                break;
        }

        Schema::defaultStringLength(191);

        return static::grammar($connection, $driver)->getSchemaBuilder();
    }

    /**
     * @param Connection $connection
     * @param string $driver
     * @return Connection
     * @throws \ReflectionException
     */
    public static function grammar(Connection $connection, string $driver)
    {
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

        In::set('connection.' . $connection->getDatabaseName(), $connection);

        return $connection;
    }
}
