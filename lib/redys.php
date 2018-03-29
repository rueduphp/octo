<?php
    namespace Octo;

    class Redys
    {
        /**
         * @param $m
         * @param $a
         *
         * @return mixed
         *
         * @throws \ReflectionException
         */
        public static function __callStatic($m, $a)
        {
            return instanciator()->singleton(Redis::class)->{$m}(...$a);
        }
    }
