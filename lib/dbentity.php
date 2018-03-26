<?php
    namespace Octo;

    class Dbentity
    {
        public function __call($m, $a)
        {
            return call_user_func_array([em($this->_entity), $m], $a);
        }

        public static function __callStatic($m, $a)
        {
            $instance = maker(get_called_class());

            return call_user_func_array([em($instance->_entity), $m], $a);
        }
    }
