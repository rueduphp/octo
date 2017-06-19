<?php
    namespace Octo;

    class Mock
    {
        private $__native, $__events = [], $__counter = [];

        public function __construct()
        {
            $args   = func_get_args();
            $native = array_shift($args);

            if (is_string($native)) {
                $this->__native = maker($native, $args);
            } else {
                $this->__native = $native;
            }

            actual('mock', $this);
        }

        public function __on($m, callable $c)
        {
            $this->__events[$m] = $c;

            return $this;
        }

        public function __native()
        {
            return $this->__native;
        }

        public function __events()
        {
            return $this->__events;
        }

        public function __set($k, $v)
        {
            $this->__native->$k = $v;
        }

        public function __get($k)
        {
            return $this->__native->$k;
        }

        public function __isset($k)
        {
            return isset($this->__native->$k);
        }

        public function __unset($k)
        {
            unset($this->__native->$k);
        }

        public function __call($m, $a)
        {
            if ($m == 'function') {
                $meth   = current($a);
                $c      = count($a) == 2 ? end($a) : null;

                if (is_callable($c)) {
                    $meth = Strings::uncamelize($meth);

                    return $this->__on($meth, $c);
                } else {
                    $counter = o([]);

                    $counter->macro('counter', function () use ($meth) {
                        if (!isset($this->__counter[$meth])) {
                            return 0;
                        }

                        return (int) $this->__counter[$meth];
                    });

                    return $counter;
                }
            }

            if ($m == 'mocked' || $m == 'default') {
                if (empty($a)) {
                    return $this->__native;
                } else {
                    $this->__native = maker(
                        get_class(
                            $this->__native
                        ), $a
                    );

                    return $this;
                }
            }

            if ($m == 'new') {
                $this->__native = maker(
                    get_class($this->__native),
                    $a
                );

                return new self($this->__native);
            }

            $c = isAke($this->__events, $m, null);

            if (!isset($this->__counter[$m])) {
                $this->__counter[$m] = 0;
            }

            if ($c) {
                if (is_callable($c)) {
                    $this->__counter[$m]++;
                    $args = array_merge($a, [$this]);

                    return call_user_func_array($c, $args);
                }
            } else {
                if (!is_null($this->__native)) {
                    $this->__counter[$m]++;
                    $args = array_merge($a, [$this]);

                    return call_user_func_array([$this->__native, $m], $args);
                } else {
                    if (!empty($a)) {
                        $c = current($a);

                        if (is_callable($c)) {
                            $m = Strings::uncamelize($m);

                            return $this->__on($m, $c);
                        }
                    }
                }
            }

            return null;
        }

        public static function __callStatic($m, $a)
        {
            $instance = actual('mock');

            if ($instance instanceof self) {
                $events = $instance->__events();
                $native = $instance->__native();

                $c = isAke($events, $m, null);

                if ($c) {
                    if (is_callable($c)) {
                        $args = array_merge($a, [$instance]);

                        return call_user_func_array($c, $args);
                    }
                } else {
                    if (!is_null($native)) {
                        $args = array_merge($a, [$instance]);

                        return call_user_func_array([$native, $m], $args);
                    } else {
                        if (!empty($a)) {
                            $c = current($a);

                            if (is_callable($c)) {
                                $m = Strings::uncamelize($m);

                                return $instance->__on($m, $c);
                            }
                        }
                    }
                }
            }

            return null;
        }
    }
