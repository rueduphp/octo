<?php

    namespace Octo;

    class Factories
    {
        private static $instances = [];

        public static function get($class, array $args = [])
        {
            $className  = str_replace('\\', '_', Strings::lower($class));
            $instance   = isAke(static::$instances, $className, null);

            if (!$instance) {
                $params = array_merge([$class], $args);

                $instance = instanciator()->make(...$params);

                static::$instances[$className] = $instance;
            }

            return $instance;
        }
    }
