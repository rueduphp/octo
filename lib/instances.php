<?php
namespace Octo;

class Instances
{
    private static $instances = [];

    /**
     * @param string $name
     * @param null $instance
     * @return mixed|null
     */
    public static function instance(string $name, $instance = null)
    {
        if (null !== $instance) {
            static::$instances[$name] = $instance;
        }

        return static::$instances[$name] ?? null;
    }

    /**
     * @param string $name
     * @param $instance
     * @return mixed|null
     */
    public static function make(string $name, $instance)
    {
        return static::instance($name, $instance);
    }

    /**
     * @param string $name
     * @param $instance
     * @return mixed|null
     */
    public static function new(string $name, $instance)
    {
        return static::instance($name, $instance);
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public static function get(string $name)
    {
        return static::instance($name);
    }

    /**
     * @param string $name
     * @return bool
     */
    public static function has(string $name)
    {
        return null !== static::get($name);
    }

    /**
     * @param string $name
     * @return bool
     */
    public static function remove(string $name)
    {
        $status = static::has($name);

        unset(static::$instances[$name]);

        return $status;
    }
}
