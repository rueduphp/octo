<?php
    namespace Octo;

    class Tests
    {
        public $assert;

        public function __construct()
        {
            $this->assert = lib('test');
        }

        public function __call($m, $a)
        {
            return call_user_func_array([$this->assert, $m], $a);
        }
    }
