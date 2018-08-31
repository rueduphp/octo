<?php
namespace App\Models;

use ArrayAccess;

class Option implements ArrayAccess
{
    /**
     * @return Settings
     */
    public static function self()
    {
        return \Octo\gi()->make(Settings::class, ['user.' . user('id')])->setStore(store('user'));
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed|null
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
     */
    public function offsetExists($offset)
    {
        return static::self()->has($offset);
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        return static::self()->get($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        static::self()->set($offset, $value);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        static::self()->delete($offset);
    }

    /**
     * @param $key
     * @param $value
     */
    public function __set($key, $value)
    {
        static::self()->set($key, $value);
    }

    /**
     * @param $key
     * @return mixed|null
     */
    public function __get($key)
    {
        return static::self()->get($key);
    }

    /**
     * @param $key
     * @return bool
     */
    public function __isset($key)
    {
        return static::self()->has($key);
    }

    /**
     * @param $key
     */
    public function __unset($key)
    {
        static::self()->delete($key);
    }
}
