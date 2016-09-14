<?php
    namespace Octo\Mongo;

    use Thin\Arrays;

    class Config
    {
        public static $items = [];

        public static function get($key, $default = null)
        {
            return Arrays::get(static::$items, $key, $default);
        }

        public static function set($key, $value = null)
        {
            static::$items = Arrays::set(static::$items, $key, $value);
        }

        public static function has($key)
        {
            $dummy = token();

            return $dummy != static::get($key, $dummy);
        }

        public static function forget($key)
        {
            if (static::has($key)) {
                arrayUnset(static::$items, $key);
            }
        }
    }
