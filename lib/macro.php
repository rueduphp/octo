<?php
    namespace Octo;

    class Macro extends \ArrayObject implements \ArrayAccess
    {
        private $_macros = [], $_data = [];

        public function __construct($metas = [])
        {
            if (is_object($metas)) {
                try {
                    $metas = $metas->toArray();
                } catch (\Exception $e) {
                    exception('macro', 'Metas must implement a class with a toArray method.');
                }
            }

            if (!is_array($metas)) {
                exception('macro', 'Metas must be an array.');
            }

            foreach ($metas as $k => $v) {
                $this->{$k} = $v;
            }
        }

        public function __set($k, $v)
        {
            if ($k == '_macros') {
                exception('macro', 'The variable _macros is protected.');
            } elseif ($k == '_data') {
                exception('macro', 'The variable _data is protected.');
            }

            if (is_callable($v)) {
                $this->_macros[$k] = $v;
                unset($this->_data[$k]);
            } else {
                if (!isset($this->_macros[$k])) $this->_data[$k] = $v;
            }
        }

        public function __unset($k)
        {
            unset($this->_macros[$k]);
            unset($this->_data[$k]);
        }

        public function __get($k)
        {
            $v = isAke(
                $this->_macros,
                $k,
                isAke(
                    $this->_data,
                    $k,
                    null
                )
            );

            return $v;
        }

        public function __isset($k)
        {
            $v = isAke($this->_macros, $k, isAke($this->_data, $k, 'octodummy'));

            return $v != 'octodummy';
        }

        public function offsetSet($k, $v)
        {
            $this->{$k} = $v;
        }

        public function offsetExists($k)
        {
            return isset($this->{$k});
        }

        public function offsetUnset($k)
        {
            unset($this->_macros[$k]);
            unset($this->_data[$k]);
        }

        public function offsetGet($k)
        {
            $v = isAke(
                $this->_macros,
                $k,
                isAke(
                    $this->_data,
                    $k,
                    null
                )
            );

            return $v;
        }

        public function __call($m, $a)
        {
            if ($m == 'array') {
                return $this->_data;
            }

            $cb = isAke($this->_macros, $m, null);

            if (is_callable($cb)) {
                $args = array_merge([$this], $a);

                return call_user_func_array($cb, $args);
            } else {
                if (!empty($a)) {
                    $this->{$m} = current($a);
                } else {
                    return isAke($this->_data, $m, null);
                }
            }
        }
    }
