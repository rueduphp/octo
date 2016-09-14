<?php
    namespace Octo;

    class Instance
    {
        private static $instances = [];

        public static function set($class, $key = null, $instance = null)
        {
            $key        = is_null($key)         ? sha1($class)  : $key;
            $instance   = is_null($instance)    ? $class        : $instance;
            $instance   = is_string($instance)  ? new $instance : $instance;

            static::$instances[$class][$key] = $instance;

            return $instance;
        }

        public static function make($class, $key = null, $instance = null)
        {
            return static::set($class, $key, $instance);
        }

        public static function has($class, $key = null)
        {
            $key            = is_null($key) ? sha1($class) : $key;
            $classInstances = isAke(static::$instances, $class, []);
            $keyInstance    = isAke($classInstances, $key, null);

            return !is_null($keyInstance);
        }

        public static function get($class, $key = null)
        {
            $key            = is_null($key) ? sha1($class) : $key;
            $classInstances = isAke(static::$instances, $class, []);
            $keyInstance    = isAke($classInstances, $key, null);

            return $keyInstance;
        }

        public static function forget($class, $key = null)
        {
            $key            = is_null($key) ? sha1($class) : $key;
            $classInstances = isAke(static::$instances, $class, []);
            $keyInstance    = isAke($classInstances, $key, null);

            if (!is_null($keyInstance)) {
                unset(static::$instances[$class][$key]);
            }
        }
    }
