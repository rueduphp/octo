<?php
    namespace Octo;

    class OWn
    {
        public static function __callStatic($m, $a)
        {
            return call_user_func_array([fmr(forever()), $ma], $a);
        }
    }
