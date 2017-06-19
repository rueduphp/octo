<?php
    namespace Octo;

    class Listener
    {
        public $priority = 0;
        public $halt = false;
        public $once = false;
        public $called = false;
        public $callable;

        public function __construct(callable $callable, $priority = 0, $halt = false, $once = false)
        {
            $this->priority = $priority;
            $this->callable = $callable;
            $this->halt     = $halt;
            $this->once     = $once;
        }

        public function once()
        {
            $this->once = !$this->once;

            return $this;
        }

        public function halt()
        {
            $this->halt = !$this->halt;

            return $this;
        }
    }
