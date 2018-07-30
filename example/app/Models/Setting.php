<?php
namespace App\Models;

use App\Services\Data;
use ArrayAccess;
use Octo\FastContainerException;
use ReflectionException;

class Setting implements ArrayAccess
{
    /**
     * @return Settings
     * @throws ReflectionException
     */
    public static function self()
    {
        return \Octo\gi()->make(Settings::class)->setStore(new Data);
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed|null
     * @throws ReflectionException
     */
    public static function __callStatic(string $name, array $arguments)
    {
        $self = static::self();

        if (in_array($name, get_class_methods($self))) {
            return $self->{$name}(...$arguments);
        }

        return $self->get($name, array_shift($arguments));
    }

    /**
     * @param mixed $offset
     * @return bool
     * @throws FastContainerException
     * @throws ReflectionException
     */
    public function offsetExists($offset)
    {
        return static::self()->has($offset);
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     * @throws FastContainerException
     * @throws ReflectionException
     */
    public function offsetGet($offset)
    {
        return static::self()->get($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @throws FastContainerException
     * @throws ReflectionException
     */
    public function offsetSet($offset, $value)
    {
        static::self()->set($offset, $value);
    }

    /**
     * @param mixed $offset
     * @throws FastContainerException
     * @throws ReflectionException
     */
    public function offsetUnset($offset)
    {
        static::self()->delete($offset);
    }

    /**
     * @param $key
     * @param $value
     * @throws ReflectionException
     */
    public function __set($key, $value)
    {
        static::self()->set($key, $value);
    }

    /**
     * @param $key
     * @return mixed|null
     * @throws ReflectionException
     */
    public function __get($key)
    {
        return static::self()->get($key);
    }

    /**
     * @param $key
     * @return bool
     * @throws ReflectionException
     */
    public function __isset($key)
    {
        return static::self()->has($key);
    }

    /**
     * @param $key
     * @throws ReflectionException
     */
    public function __unset($key)
    {
        static::self()->delete($key);
    }
}
