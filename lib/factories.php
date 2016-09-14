<?php

    namespace Octo;

    class Factories
    {
        private static $instances = [];

        public static function get($class, $args = [])
        {
            $className  = str_replace('\\', '_', Strings::lower($class));
            $instance   = isAke(static::$instances, $className, null);

            if (!$instance) {
                if (fnmatch('octo_*', $className) || !fnmatch('*_*', $className)) {
                    $instance = lib($class, $args);
                } else {
                    $instance = (new App)->make($class, $args);
                }

                static::$instances[$className] = $instance;
            }

            return $instance;
        }
    }
