<?php
namespace Octo;

use Illuminate\Events\Dispatcher;

class Db
{
    /**
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     *
     * @throws \ReflectionException
     */
    public static function __callStatic(string $name, array $arguments)
    {
        if ('sql' === $name) {
            $name = 'select';
        }

        return static::start()->{$name}(...$arguments);
    }

    /**
     * @return \Illuminate\Database\Connection
     * @throws \ReflectionException
     */
    public static function start()
    {
        $connection = Capsule::connection();

        $connection->setEventDispatcher(static::getEventDispatcher());

        if (!In::has('db')) {
            In::set('db', $connection);
        }

        return $connection;
    }


    /**
     * @param string $ns
     * @return Dispatcher
     * @throws \ReflectionException
     */
    public static function getEventDispatcher($ns = 'capsule.dispatcher')
    {
        if (!$dispatcher = get($ns)) {
            $dispatcher = new Dispatcher;
            set($ns, $dispatcher);
        }

        return $dispatcher;
    }
}