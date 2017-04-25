<?php
    namespace Octo;

    class Dbentity
    {
        public static function __call($m, $a)
        {
            return call_user_func_array([em($this->_entity), $m], $a);
        }

        public static function __callStatic($m, $a)
        {
            return call_user_func_array([em($this->_entity), $m], $a);
        }
    }
