<?php
    namespace Octo;

    class My implements \ArrayAccess
    {
        private $ns;
        private static $instances = [];

        public function __construct($ns = 'web')
        {
            $this->ns = $ns;
        }

        public function __toString()
        {
            return json_encode($this->all());
        }

        public function __invoke($k)
        {
            return $this->get($k);
        }

        private function makeKey($k)
        {
            return $this->ns . '.' . $k . '.' . str_replace('_', '.', forever());
        }

        public function flush()
        {
            return fmr('my')->flush($this->ns . '.*.' . str_replace('_', '.', forever()));
        }

        public function all()
        {
            $keys = fmr('my')->keys($this->ns . '.*.' . str_replace('_', '.', forever()));

            $collection = [];

            foreach ($keys as $key) {
                list($ns, $k, $forever) = explode('.', $key, 3);
                yield [$k => fmr('my')->get($key)];
            }
        }

        public function __set($k, $v)
        {
            return $this->set($k, $v);
        }

        public function __get($k)
        {
            return $this->get($k);
        }

        public function __isset($k)
        {
            return $this->has($k);
        }

        public function __unset($k)
        {
            return $this->delete($k);
        }

        public function set($k, $v)
        {
            fmr('my')->set($this->makeKey($k), $v);

            return $this;
        }

        public function get($k, $default = null)
        {
            return fmr('my')->get($this->makeKey($k), $default);
        }

        public function has($k)
        {
            return fmr('my')->has($this->makeKey($k));
        }

        public function offsetSet($k, $v)
        {
            return $this->set($k, $v);
        }

        public function offsetGet($k)
        {
            return $this->get($k);
        }

        public function offsetExists($k)
        {
            return $this->has($k);
        }

        public function offsetUnset($k)
        {
            return $this->delete($k);
        }

        public function delete($k)
        {
            fmr('my')->del($this->makeKey($k));

            return $this;
        }

        public function forget($k)
        {
            return $this->delete($k);
        }

        public function remove($k)
        {
            return $this->delete($k);
        }

        public function del($k)
        {
            return $this->delete($k);
        }

        public function erase($k)
        {
            return $this->delete($k);
        }

        public static function __callStatic($m, $a)
        {
            $i = self::instance();

            return call_user_func_array([$i, $m], $a);
        }

        public function __call($m, $a)
        {
            if (fnmatch('get*', $m) && strlen($m) > strlen('get')) {
                $k = Inflector::uncamelize(substr($m, 3));

                $default = empty($a) ? null : current($a);

                return $this->get($k, $default);
            } elseif (fnmatch('set*', $m) && strlen($m) > strlen('set')) {
                $k = Inflector::uncamelize(substr($m, 3));

                return $this->set($k, current($a));
            } elseif (fnmatch('has*', $m) && strlen($m) > strlen('has')) {
                $k = Inflector::uncamelize(substr($m, 3));

                return $this->has($k);
            } elseif (fnmatch('del*', $m) && strlen($m) > strlen('del')) {
                $k = Inflector::uncamelize(substr($m, 3));

                return $this->del($k);
            }  elseif (fnmatch('erase*', $m) && strlen($m) > strlen('erase')) {
                $k = Inflector::uncamelize(substr($m, 3));

                return $this->del($k);
            } else {
                $closure = $this->get($m);

                if (is_string($closure) && fnmatch('*::*', $closure)) {
                    list($c, $f) = explode('::', $closure, 2);

                    try {
                        $i = lib('caller')->make($c);

                        return call_user_func_array([$i, $f], $a);
                    } catch (\Exception $e) {
                        $default = empty($a) ? null : current($a);

                        return empty($closure) ? $default : $closure;
                    }
                } else {
                    if (is_callable($closure)) {
                        return call_user_func_array($closure, $a);
                    }

                    if (!empty($a) && empty($closure)) {
                        if (count($a) == 1) {
                            return $this->set($m, current($a));
                        }
                    }

                    $default = empty($a) ? null : current($a);

                    return empty($closure) ? $default : $closure;
                }
            }
        }

        public static function instance($ns = 'web')
        {
            $i = isAke(self::$instances, $ns, false);

            if (!$i) {
                $i = new self($ns);

                self::$instances[$ns] = $i;
            }

            return $i;
        }

        public function getOr($key, callable $c)
        {
            if (!$this->has($key)) {
                $value = $c();

                $this->set($key, $value);

                return $value;
            }

            return $this->get($key);
        }

        public function flash($key, $val = 'octodummy')
        {
            $this->check();
            $key = "flash_{$key}";

            if ($val != 'octodummy') {
                $this->set($key, $val);
            } else {
                $val = $this->get($key);
                $this->delete($key);
            }

            return $val != 'octodummy' ? $this : $val;
        }

        public function fill(array $data)
        {
            foreach ($data as $k => $v) {
                $this->set($k, $v);
            }

            return $this;
        }

        public function hydrate($data = null)
        {
            if (!is_array($data)) {
                $data = $_POST;
            }

            foreach ($data as $k => $v) {
                $this->set($k, $v);
            }

            return $this;
        }

        public function incr($k, $by = 1)
        {
            $old = $this->get($k, 0);
            $new = $old + $by;

            $this->set($k, $new);

            return $new;
        }

        public function increment($k, $by = 1)
        {
            return $this->incr($k, $by);
        }

        public function decr($k, $by = 1)
        {
            $old = $this->get($k, 0);
            $new = $old - $by;

            $this->set($k, $new);

            return $new;
        }

        public function decrement($k, $by = 1)
        {
            return $this->decr($k, $by);
        }

        public function login()
        {
            $this->set('logged', true);

            return $this;
        }

        public function logout()
        {
            $this->set('logged', false);

            return $this;
        }

        public function isLogged()
        {
            if ($thos->has('loggud')) {
                return $this->get('logged', false);
            }

            return false;
        }
    }

