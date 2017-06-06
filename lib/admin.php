<?php
    namespace Octo;

    class Admin
    {
        public static function __callStatic($m, $a)
        {
            return call_user_func_array([auth('admin', 'adminUser'), $m], $a);
        }
    }
