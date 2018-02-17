<?php
    namespace Octo;

    class Dyn
    {
        private $native, $events = [];

        public function __construct($native = null, $args = [])
        {
            if (is_string($native)) {
                $this->native = maker($native, $args);
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

        /**
         * @param $m
         * @param $a
         *
         * @return mixed|null|Dyn
         *
         * @throws \ReflectionException
         */
        public function __call($m, $a)
        {
            $c = isAke($this->events, $m, null);

            if ($c) {
                if (is_callable($c)) {
                    $methods    = get_class_methods($this->native);
                    $has_method = in_array($m, $methods);

                    if (!$has_method) {
                        $args = array_merge($a, [$this]);

                        $params = array_merge([$c], $args);

                        return callCallable(...$params);
                    } else {
                        $params = array_merge([$this->native, $m], $a);

                        $res = instanciator()->call(...$params);

                        return callCallable($c, $res);
                    }
                }
            } else {
                if (!is_null($this->native)) {
                    $params = array_merge([$this->native, $m], $a);

                    return instanciator()->call(...$params);
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
