<?php
    namespace Octo;

    class Base
    {
        public function __call($m, $a)
        {
            if (function_exists('\\Octo\\' . $m)) {
                return call_user_func_array('\\Octo\\' . $m, $a);
            }
        }

        public static function __callStatic($m, $a)
        {
            if (function_exists('\\Octo\\' . $m)) {
                return call_user_func_array('\\Octo\\' . $m, $a);
            }
        }
    }
