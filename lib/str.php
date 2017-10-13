<?php
    namespace Octo;

    class Str
    {
        public static function __callStatic($m, $a)
        {
            return call_user_func_array([lib('inflector'), $m], $a);
        }
    }
