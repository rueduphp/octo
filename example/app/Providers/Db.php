<?php
namespace App\Providers;

use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Redis\RedisManager;
use Octo\Arrays;
use Octo\Capsule;
use Octo\Dynamicmodel;
use Octo\Facades\Config as CoreConf;
use Octo\Orm;
use PDO;

class Db
{
    /**
     * @throws \ReflectionException
     */
    public function handler()
    {
        $db     = CoreConf::get('db');
        $redis  = CoreConf::get('redis');

        $default = $db['default'];

        $conf = $db[$default];
        $lite = $db["sqlite"];

        $PDOoptions = [
            PDO::ATTR_CASE                 => PDO::CASE_NATURAL,
            PDO::ATTR_ERRMODE              => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_ORACLE_NULLS         => PDO::NULL_NATURAL,
            PDO::ATTR_DEFAULT_FETCH_MODE   => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES    => false,
            PDO::ATTR_EMULATE_PREPARES     => false,
        ];

        $pdo = new PDO(
            "{$conf['driver']}:host={$conf['host']};dbname={$conf['database']}",
            $conf['username'],
            $conf['password'],
            $PDOoptions
        );

        inInstance($pdo, 'main.pdo');

        Capsule::instance($pdo);

        l('config')->set([
            'database' => [
                'default' => $default,
                'connections' => [
                    $default => [
                        'driver'        => $conf['driver'],
                        'host'          => $conf['host'],
                        'port'          => $conf['port'],
                        'database'      => $conf['database'],
                        'username'      => $conf['username'],
                        'password'      => $conf['password'],
                        'unix_socket'   => '',
                        'charset'       => $conf['charset'] ?? 'utf8',
                        'collation'     => $conf['collation'] ?? 'utf8_unicode_ci',
                        'prefix'        => '',
                        'strict'        => true,
                        'engine'        => null,
                    ],
                    'sqlite' => [
                        'driver'    => 'sqlite',
                        'database'  => $lite['database'],
                        'prefix'    => $lite['prefix'] ?? '',
                    ]
                ],
                'redis' => [

                    'client' => 'predis',

                    'default'       => [
                        'host'      => $redis['host'],
                        'password'  => $redis['password'],
                        'port'      => $redis['port'],
                        'database'  => $redis['database'],
                    ],
                ],
            ]
        ]);

        l(
            'db',
            new DatabaseManager(
                l(),
                new ConnectionFactory(l())
            )
        );

        dic('db', l('db'));

        dic('orm', new Orm($pdo));

        Dynamicmodel::migrate();

        /* REDIS */
        l()->singleton('redis', function ($app) {
            $config = $app->make('config')->get('database.redis');

            return new RedisManager(Arrays::pull($config, 'client', 'predis'), $config);
        });

        l()->bind('redis.connection', function ($app) {
            return $app['redis']->connection();
        });

        dic('redis', l('redis'));

        dic('redis.connection', l('redis.connection'));
    }
}
