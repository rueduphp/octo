<?php
    namespace Octo;

    class Auth
    {
        public static function __callStatic($m, $a)
        {
            return call_user_func_array([auth(), $m], $a);
        }
    }
