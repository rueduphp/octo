<?php
    namespace {
        exit("Only for IDE");

        /**
         * @method static mixed get(string $key, $default = null)
         * @method static Octo\Now set(string $key, $value)
         * @method static bool has(string $key)
         * @method static bool del(string $key)
         * @method static int incr(string $key)
         * @method static int decr(string $key)
         */
        class Registry
        {
            public static function __callStatic($method, $args)
            {
                return (new Octo\Now)->{$method}(...$args);
            }
        }

        class Cache
        {
            public static function __callStatic($method, $args)
            {
                return (new Octo\Cache)->{$method}(...$args);
            }
        }

        class Strings
        {
            public static function __callStatic($method, $args)
            {
                return (new Octo\Inflector)->{$method}(...$args);
            }
        }

        class Dir
        {
            public static function __callStatic($method, $args)
            {
                return (new Octo\File)->{$method}(...$args);
            }
        }

        class Arrays
        {
            public static function __callStatic($method, $args)
            {
                return (new Octo\Arrays)->{$method}(...$args);
            }
        }

        class Timer
        {
            public static function __callStatic($method, $args)
            {
                return (new Octo\Timer)->{$method}(...$args);
            }
        }

        class Time
        {
            public static function __callStatic($method, $args)
            {
                return (new Octo\Time)->{$method}(...$args);
            }
        }

        class ORM extends Octo\Octal {}

        class System
        {
            public static function __callStatic($method, $args)
            {
                $table = Strings::uncamelize($method);

                if (empty($args)) {
                    return Octo\em(Strings::camelize("system_" . $table));
                } elseif (count($args) == 1) {
                    $id = array_shift($args);

                    if (is_numeric($id)) {
                        return Octo\em(Strings::camelize("system_" . $table))->find($id);
                    }
                }
            }
        }
    }

    namespace Octo {
        /**
         * @method static mixed get(string $key, $default = null)
         * @method static Now set(string $key, $value)
         * @method static bool has(string $key)
         * @method static bool del(string $key)
         * @method static int incr(string $key)
         * @method static int decr(string $key)
         */
        class Registry
        {
            public static function __callStatic($method, $args)
            {
                return (new Now)->{$method}(...$args);
            }
        }

        class System
        {
            public static function __callStatic($method, $args)
            {
                $table = Strings::uncamelize($method);

                if (empty($args)) {
                    return em(Strings::camelize("system_" . $table));
                } elseif (count($args) == 1) {
                    $id = array_shift($args);

                    if (is_numeric($id)) {
                        return em(Strings::camelize("system_" . $table))->find($id);
                    }
                }
            }
        }

        /**
         * @method static string unaccent(string $concern)
         * @method static string camelize(string $concern)
         * @method static string uncamelize(string $concern)
         * @method static string upper(string $concern)
         * @method static string lower(string $concern)
         * @method static string urlize(string $concern, string $separator = '-')
         */
        class Strings
        {
            public static function __callStatic($method, $args)
            {
                return (new Inflector)->{$method}(...$args);
            }
        }

        class Dir
        {
            public static function __callStatic($method, $args)
            {
                return (new File)->{$method}(...$args);
            }
        }
    }
