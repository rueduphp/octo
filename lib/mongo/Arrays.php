<?php
    namespace Octo\Mongo;

    class Arrays implements \Countable, \IteratorAggregate
    {
        private $cursor;

        public function __construct($cursor)
        {
            $this->cursor = $cursor;
        }

        public function getCursor()
        {
            return $this->cursor;
        }

        public function current()
        {
            $current = $this->cursor->current();

            return $current;
        }

        public function getNext()
        {
            $next = $this->getCursor()->getNext();

            unset($next['_id']);

            return $next;
        }

        public function getIterator()
        {
            return $this->cursor;
        }

        public function count()
        {
            return $this->cursor->count();
        }

        public function toArray()
        {
            return iterator_to_array($this->cursor);
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
