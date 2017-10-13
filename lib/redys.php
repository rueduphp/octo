<?php
    namespace Octo;

    class Redys
    {
        public static function __callStatic($m, $a)
        {
            return call_user_func_array([maker(Redis::class), $m], $a);
        }
    }
