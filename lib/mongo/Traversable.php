<?php
    namespace Octo\Mongo;

    class Traversable
    {
        private $cursor;

        public function __construct($cursor)
        {
            $this->cursor = $cursor;
        }

        public function __call($method, $arguments)
        {
            $function   = [$this->cursor, $method];
            $result     = call_user_func_array($function, $arguments);

            if ($result instanceof \MongoCursor) {
                return $this;
            }

            return $result;
        }
    }
