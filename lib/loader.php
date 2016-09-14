<?php
    namespace Octo;

    class Loader
    {
        protected static $directories = [];

        protected static $instance = false;

        public static function load($class)
        {
            $class = static::clean($class);

            foreach (static::$directories as $directory) {
                if (file_exists($path = $directory . DS . $class)) {
                    require_once $path;

                    return true;
                }
            }

            return false;
        }

        public static function clean($class)
        {
            if ($class[0] == '\\') $class = substr($class, 1);

            return str_replace(['\\', '_'], DS, $class) . '.php';
        }

        public static function register()
        {
            if (!static::$instance) {
                static::$instance = spl_autoload_register(['\Octo\Loader', 'load']);
            }
        }

        public static function addDirectories($directories)
        {
            static::$directories = array_unique(
                array_merge(
                    static::$directories,
                    (array) $directories
                )
            );
        }

        public static function removeDirectories($directories = null)
        {
            if (is_null($directories)) {
                static::$directories = [];
            } else {
                static::$directories = array_diff(static::$directories, (array) $directories);
            }
        }

        public static function getDirectories()
        {
            return static::$directories;
        }

        public static function put($directory)
        {
            static::$directories[] = $directory;
        }
    }
