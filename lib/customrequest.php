<?php
    namespace Octo;

    class CustomRequest implements \ArrayAccess
    {
        public function __construct()
        {
            $all = Input::method()->toArray();

            foreach ($all as $key => $value) {
                $this->$key = $value;
            }

            $methods = get_class_methods($this);

            if (in_array('boot', $methods)) {
                $this->boot();
            }

            if (in_array('authorize', $methods)) {
                $check = $this->authorize();

                if (!$check) {
                    exception('Request', 'Access forbidden');
                }
            }
        }

        public function language()
        {
            return Request::language();
        }

        public function method()
        {
            return Request::method();
        }

        public function ip()
        {
            return Request::ip();
        }

        public function user($admin = false)
        {
            return !$admin ? Auth::user() : Admin::user();
        }

        public function __set($k, $v)
        {
            $this->$k = $v;
        }

        public function offsetSet($k, $v)
        {
            $this->$k = $v;
        }

        public function __get($k)
        {
            return $this->$k;
        }

        public function offsetGet($k)
        {
            return $this->$k;
        }

        public function __isset($k)
        {
            return isset($this->$k);
        }

        public function offsetExists($k)
        {
            return isset($this->$k);
        }

        public function __unset($k)
        {
            unset($this->$k);
        }

        public function offsetUnset($k)
        {
            unset($this->$k);
        }

        public function toArray()
        {
            return (array) $this;
        }

        public function toJson($option = JSON_PRETTY_PRINT)
        {
            return json_encode($this->toArray(), $option);
        }

        public function __toString()
        {
            return $this->toJson();
        }

        public function only($keys)
        {
            $keys = is_array($keys) ? $keys : func_get_args();

            return array_intersect_key($this->toArray(), array_flip((array) $keys));
        }

        public function except($keys)
        {
            $keys = is_array($keys) ? $keys : func_get_args();

            return array_diff_key($this->toArray(), array_flip((array) $keys));
        }

        public function __call($m, $a)
        {
            if (fnmatch('get*', $m) && strlen($m) > 3) {
                $key = Inflector::uncamelize(substr($m, 3));
                $default = empty($a) ? null : current($a);

                return isset($this->$key) ? $this->$key : $default;
            } elseif (fnmatch('set*', $m) && strlen($m) > 3) {
                $key = Inflector::uncamelize(substr($m, 3));

                $this->$key = current($a);

                return $this;
            } elseif (fnmatch('has*', $m) && strlen($m) > 3) {
                $key = Inflector::uncamelize(substr($m, 3));

                return isset($this->$key);
            }
        }
    }
