<?php
    namespace Octo;

    class Isred
    {
        public static function __callStatic($m, $a)
        {
            return call_user_func_array([lib("redis"), $m], $a);
        }

        public static function __call($m, $a)
        {
            return call_user_func_array([lib("redis"), $m], $a);
        }
    }
