<?php
    namespace Octo;

    class Own
    {
        /**
         * @param $name
         * @param $arguments
         *
         * @return mixed
         *
         * @throws Exception
         */
        public static function __callStatic($name, $arguments)
        {
            return fmr(forever())->{$name}(...$arguments);
        }
    }
