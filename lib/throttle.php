<?php
    namespace Octo;

    class Throttle
    {
        private static $instances   = [];
        public $resource, $limit, $lifetime;

        public function get($resource, $limit = 50, $lifetime = 30)
        {
            if (!$instance = isAke(self::$instances, $resource, false)) {
                $instance = new self;

                $instance->resource = $resource;
                $instance->limit    = $limit;
                $instance->lifetime = $lifetime;

                self::$instances[$resource] = $instance;

                Own::set($resource, 0);
                Own::set($resource . '.time', time() + $lifetime);
            }

            return $instance;
        }

        public function incr($by = 1)
        {
            $this->evaluate();

            Own::incr($this->resource, $by);
        }

        public function decr($by = 1)
        {
            $this->evaluate();

            Own::decr($this->resource, $by);
        }

        public function check()
        {
            $check = Own::get($this->resource, 0) <= $this->limit;

            $this->evaluate();

            return $check;
        }

        public function clear()
        {
            Own::set($this->resource . '.time', time() + $this->lifetime);

            return Own::set($this->resource, 0);
        }

        public function attempt($by = 1)
        {
            $this->incr($by);

            return $this->check();
        }

        private function evaluate()
        {
            $when = Own::get($this->resource . '.time');

            if ($when < time()) {
                Own::set($this->resource . '.time', time() + $this->lifetime);

                return Own::set($this->resource, 0);
            }
        }
    }
