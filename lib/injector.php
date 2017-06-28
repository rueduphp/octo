<?php
    namespace Octo;

    class Injector
    {
        public static function instance($class, $args = [])
        {
            return maker($class, $args, false);
        }

        public static function once($class, $args = [])
        {
            return maker($class, $args, true);
        }

        public static function singleton($class, $args = [])
        {
            return maker($class, $args, true);
        }

        public static function method()
        {
            return call_user_func_array('\\Octo\\callMethod', func_get_args());
        }

        public static function __callStatic($m, $a)
        {
            if ($m == 'new' || $m == 'class') {
                $class  = array_shift($a);
                $args   = array_shift($a);

                $args = !is_array($args) ? [] : $args;

                return maker($class, $args, false);
            } elseif ($m == 'function') {
                return call_user_func_array('\\Octo\\callMethod', $a);
            }
        }
    }
