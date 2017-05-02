<?php
    namespace Octo;

    class EntityManager
    {
        public static function __callStatic($m, $a)
        {
            $class = array_shift($a);

            $instance = maker($class);

            return call_user_func_array([$instance, $m], $a);
        }
    }
