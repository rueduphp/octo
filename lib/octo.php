<?php
    namespace Octo;

    class Octo
    {
        public static function instance()
        {
            return context('app');
        }

        public static function get($concern = null)
        {
            $app = context('app');

            return $concern ? $app[$concern] : $app;
        }

        public static function queue($class, array $args = [], $queue = 'default', $time = 0)
        {
            return Async::add($class, $args, $queue, $time);
        }

        public static function __callStatic($m, $a)
        {
            return call_user_func_array([context('app'), $m], $a);
        }
    }
