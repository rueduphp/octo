<?php
    namespace Octo;

    class Make
    {
        public function __call($method, $args)
        {
            return call_user_func_array([(new Now('dic')), $method], $args);
        }

        public static function __callStatic($method, $args)
        {
            return call_user_func_array([(new Now('dic')), $method], $args);
        }
    }
