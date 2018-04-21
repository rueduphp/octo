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

        $connection = Capsule::connection();

        $connection->setEventDispatcher(static::getEventDispatcher());

        In::set('db', $connection);

        return $connection->{$name}(...$arguments);
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