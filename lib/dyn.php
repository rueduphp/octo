<?php
    namespace Octo;

    class Dyn
    {
        private $native, $events = [];

        public function __construct($native = null)
        {
            $this->native = $native;
        }

        public function fn($m, callable $c)
        {
            $this->events[$m] = $c;

            return $this;
        }

        public function extend($m, callable $c)
        {
            return $this->fn($m, $c);
        }

        public function macro($m, callable $c)
        {
            return $this->fn($m, $c);
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
                    $methods    = get_class_methods($this->native);
                    $has_method = in_array($m, $methods);

                    if (!$has_method) {
                        $args = array_merge($a, [$this]);

                        return call_user_func_array($c, $args);
                    } else {
                        $res = call_user_func_array([$this->native, $m], $a);

                        return call_user_func_array($c, [$res]);
                    }
                }
            } else {
                if (!is_null($this->native)) {
                    return call_user_func_array([$this->native, $m], $a);
                } else {
                    if (!empty($a)) {
                        $c = current($a);

                        if (is_callable($c)) {
                            $m = Strings::uncamelize($m);

                            return $this->fn($m, $c);
                        }
                    }
                }
            }

            return null;
        }
    }
