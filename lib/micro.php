<?php
    namespace Octo;

    class Micro extends \ArrayObject implements \ArrayAccess
    {
        private $page;

        public function attach(Page $page)
        {
            $this->page = $page;

            return $this;
        }

        public function app()
        {
            return $this->page;
        }

        public function __set($k, $v)
        {
            $this->page->{$k} = $v;
        }

        public function __unset($k)
        {
            unset($this->page->{$k});
        }

        public function __get($k)
        {
            if (property_exists($this->page, $k)) {
                return $this->page->{$k};
            }

            return null;
        }

        public function __isset($k)
        {
            return property_exists($this->page, $k);
        }

        public function offsetSet($k, $v)
        {
            $this->page->{$k} = $v;
        }

        public function offsetExists($k)
        {
            return property_exists($this->page, $k);
        }

        public function offsetUnset($k)
        {
            unset($this->page->{$k});
        }

        public function offsetGet($k)
        {
            if (property_exists($this->page, $k)) {
                return $this->page->{$k};
            }

            return null;
        }

        public function __call($m, $a)
        {
            if (property_exists($this->page, $m)) {
                if (is_callable($this->page->{$m})) {
                    return call_user_func_array($this->page->{$m}, $a);
                }
            }
        }
    }
