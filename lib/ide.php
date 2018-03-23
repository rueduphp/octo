<?php
    namespace {
        exit("Only for IDE");

        class Registry
        {
            public static function __callStatic($method, $args)
            {
                return call_user_func_array([(new Octo\Now), $method], $args);
            }
        }

        class Cache
        {
            public static function __callStatic($method, $args)
            {
                return call_user_func_array([(new Octo\Cache), $method], $args);
            }
        }

        class Strings
        {
            public static function __callStatic($method, $args)
            {
                return call_user_func_array([(new Octo\Inflector), $method], $args);
            }
        }

        class Dir
        {
            public static function __callStatic($method, $args)
            {
                return call_user_func_array([(new Octo\Inflector), $method], $args);
            }
        }

        class Arrays
        {
            public static function __callStatic($method, $args)
            {
                return call_user_func_array([(new Octo\Arrays), $method], $args);
            }
        }

        class Timer
        {
            public static function __callStatic($method, $args)
            {
                return call_user_func_array([(new Octo\Timer), $method], $args);
            }
        }

        class Time
        {
            public static function __callStatic($method, $args)
            {
                return call_user_func_array([(new Octo\Time), $method], $args);
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
        class Registry
        {
            public static function __callStatic($method, $args)
            {
                return call_user_func_array([(new Now), $method], $args);
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
         * @method static string urlize()
         */
        class Strings
        {
            public static function __callStatic($method, $args)
            {
                return call_user_func_array([(new Inflector), $method], $args);
            }
        }

        class Dir
        {
            public static function __callStatic($method, $args)
            {
                return call_user_func_array([(new Inflector), $method], $args);
            }
        }
    }
