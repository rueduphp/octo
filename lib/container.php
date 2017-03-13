<?php
    namespace Octo;

    class Container extends Ghost
    {
        public function __construct(array $values = [])
        {
            parent::__construct($values, 'container');
        }

        public static function __callStatic($m, $a)
        {
            $i = new static;

            return call_user_func_array();
        }
    }
