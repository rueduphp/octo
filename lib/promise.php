<?php
    namespace Octo;

    class Promise
    {
        private $events = [];
        private $result = null;

        public function __construct($promise = null)
        {
            if (is_callable($promise)) {
                $this->then($promise);
            }
        }

        public function then(callable $event)
        {
            $this->events[] = $event;

            return $this;
        }

        public function resolve()
        {
            $event = array_shift($this->events);

            if (is_callable($event)) {
                $this->result = $event($this->result);
            }

            if (!empty($this->events)) {
                $this->resolve();
            }
        }
    }
