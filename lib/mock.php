<?php
    namespace Octo;

    class Mock
    {
        private $__native, $__events = [];

        public function __construct($native, array $args = [])
        {
            if (is_string($native)) {
                $this->__native = app()->make($native, $args);
            } else {
                $this->__native = $native;
            }

            Registry::set('last_mock', $this);
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
            $c = isAke($this->__events, $m, null);

            if ($c) {
                if (is_callable($c)) {
                    $args = array_merge($a, [$this]);

                    return call_user_func_array($c, $args);
                }
            } else {
                if (!is_null($this->__native)) {
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
            $instance = Registry::get('last_mock');

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
