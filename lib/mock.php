<?php
    namespace Octo;

    class Mock
    {
        private $native, $events = [];

        public function __construct($native, array $args = [])
        {
            if (is_string($native)) {
                $this->native = (new App)->make($native, $args);
            } else {
                $this->native = $native;
            }
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
                    $args = array_merge($a, [$this]);

                    return call_user_func_array($c, $args);
                }
            } else {
                if (!is_null($this->native)) {
                    return call_user_func_array([$this->native, $m], $a);
                } else {
                    if (!empty($a)) {
                        $c = current($a);

                        if (is_callable($c)) {
                            $m = Inflector::uncamelize($m);

                            return $this->fn($m, $c);
                        }
                    }
                }
            }

            return null;
        }
    }
