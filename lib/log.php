<?php
    namespace Octo;

    class Log
    {
        public static function __callStatic($m, $a)
        {
            $message    = array_shift($a);
            $ns         = array_shift($a);

            return log($message, $m, $ns);
        }
    }
