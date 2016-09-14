<?php
    namespace Octo\Mongo;

    class Facade
    {
        public static function __callStatic($method, $args)
        {
            $instance = Db::instance(static::$database, static::$table);

            return call_user_func_array([$instance, $method], $args);
        }
    }
