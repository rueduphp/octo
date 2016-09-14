<?php
    namespace Octo;

    class Stub
    {
        private $native, $events = [];

        public function on($native = null)
        {
            $this->native = $native;

            return $this;
        }

        public function hook($m, callable $c)
        {
            $methods = get_class_methods($this->native);

            $has_method = in_array($m, $methods);

            if (!$has_method) {
                exception('Stub', "This method does not exist.");
            }

            $this->events[$m] = $c;

            return $this;
        }

        public function getNative()
        {
            return $this->native;
        }

        public function __call($m, $a)
        {
            $c = isAke($this->events, $m, null);

            if ($c) {
                if (is_callable($c)) {
                    return call_user_func_array($c, $a);
                }
            }

            return null;
        }
    }
