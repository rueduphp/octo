<?php
    namespace Octo;

    use ArrayAccess as AA;

    class Bag implements AA
    {
        private static $data = [];

        public function __construct()
        {
            $session = getSession();

            self::$data = $session['octobag'] ?? [];
        }

        public function __destruct()
        {
            $session = getSession();

            $session['octobag'] = self::$data;
        }

        public function flush()
        {
            self::$data = [];

            return $this;
        }

        public function fill($rows = [])
        {
            $data = self::$data;

            $data = array_merge($data, $rows);

            self::$data = $data;

            return $this;
        }

        public function __set($k, $v)
        {
            self::$data[$k] = $v;

            return $this;
        }

        public function __get($k)
        {
            if (isset(self::$data[$k])) {
                return self::$data[$k];
            }

            return null;
        }

        public function __isset($k)
        {
            return isset(self::$data[$k]);
        }

        public function __unset($k)
        {
            unset(self::$data[$k]);

            return $this;
        }

        public function set($k, $v)
        {
            self::$data[$k] = $v;

            return $this;
        }

        public function add($k, $v)
        {
            return $this->set($k, $v);
        }

        public function put($k, $v)
        {
            return $this->set($k, $v);
        }

        public function flash($k, $v = null)
        {
            $k = 'flash.' . $k;

            if (is_null($v)) {
                return $this->getDel($k);
            }

            return $this->set($k, $v);
        }

        public function offsetSet($k, $v)
        {
            self::$data[$k] = $v;

            return $this;
        }

        public function get($k, $default = null)
        {
            if (isset(self::$data[$k])) {
                return self::$data[$k];
            }

            return $default;
        }

        public function cursor($k, $default = [])
        {
            $data = !isset(self::$data[$k]) ? $default : self::$data[$k];

            foreach ($data as $row) {
                yield $row;
            }
        }

        public function getOr($k, callable $c)
        {
            if ($this->has($k)) {
                return $this->get($k);
            }

            $res = $c();

            return $this->set($k, $res);
        }

        public function listen($k, callable $c)
        {
            $k = "event.$k";
            self::$data[$k] = $c;

            return $this;
        }

        public function fire($k, $args = [], $d = null)
        {
            $k = "event.$k";
            $c = isAke(self::$data, $k, $d);

            if (is_callable($c)) {
                return call_user_func_array($c, $args);
            }

            return $d;
        }

        public function offsetGet($k)
        {
            if ($this->has($k)) {
                return $this->get($k);
            }

            return null;
        }

        public function has($k)
        {
            return isset(self::$data[$k]);
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
            if ($this->has($k)) {
                unset(self::$data[$k]);

                return true;
            }

            return false;
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

        public function __call($m, $a)
        {
            if (fnmatch('get*', $m)) {
                $key = Inflector::uncamelize(substr($m, 3));
                $default = empty($a) ? null : current($a);

                return $this->get($key, $default);
            } elseif (fnmatch('set*', $m)) {
                $key = Inflector::uncamelize(substr($m, 3));

                return $this->set($key, current($a));

            } elseif (fnmatch('has*', $m)) {
                $key = Inflector::uncamelize(substr($m, 3));

                return $this->has($key);

            } elseif (fnmatch('del*', $m)) {
                $key = Inflector::uncamelize(substr($m, 3));

                return $this->delete($key);
            } else {
                $closure = $this->get($m);

                if (is_string($closure) && fnmatch('*::*', $closure)) {
                    list($c, $f) = explode('::', $closure, 2);

                    try {
                        return call_user_func_array([app($c), $f], $a);
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

        public static function getInstance($ns = 'core', array $data = [])
        {
            if (!isset(self::$data[$ns])) {
                self::$data[$ns] = new self($ns, $data);
            }

            return self::$data[$ns];
        }

        public function incr($name, $by = 1)
        {
            $old = $this->get($name, 0);
            $new = $old + $by;

            $this->set($name, $new);

            return (int) $new;
        }

        public function decr($name, $by = 1)
        {
            $old = $this->get($name, 1);
            $new = $old - $by;

            $this->set($name, $new);

            return (int) $new;
        }

        public function increment($name, $by = 1)
        {
            $old = $this->get($name, 0);
            $new = $old + $by;

            $this->set($name, $new);

            return (int) $new;
        }

        public function decrement($name, $by = 1)
        {
            $old = $this->get($name, 1);
            $new = $old - $by;

            $this->set($name, $new);

            return (int) $new;
        }

        public function in($name, $data)
        {
            $key = 'tuples.' . $name;
            $check = sha1(serialize($data));

            $tab = $this->get($key, []);

            if (!in_array($check, $tab)) {
                $tab[] = $check;

                $this->set($key, $tab);

                return false;
            }

            return true;
        }

        public function hset($h, $k, $v)
        {
            return $this->set("hash.$h.$k", $v);
        }

        public function hget($h, $k, $d)
        {
            return $this->get("hash.$h.$k", $d);
        }

        public function hgetOr($h, $k, callable $c)
        {
            return $this->getOr("hash.$h.$k", $c);
        }

        public function hhas($h, $k, $d)
        {
            return $this->has("hash.$h.$k");
        }

        public function hdel($h, $k, $d)
        {
            return $this->delete("hash.$h.$k");
        }

        public function hincr($hash, $key, $by = 1)
        {
            $old = $this->hget($hash, $key, 1);
            $new = $old + $by;

            $this->hset($hash, $key, $new);

            return $new;
        }

        public function hdecr($hash, $key, $by = 1)
        {
            $old = $this->hget($hash, $key, 1);
            $new = $old - $by;

            $this->hset($hash, $key, $new);

            return $new;
        }

        public function keys($pattern = '*')
        {
            $data = self::$data;

            foreach ($data as $k => $v) {
                if (fnmatch($patter, $k)) {
                    yield $k;
                }
            }
        }

        public function hgetall($hash)
        {
            $data = self::$data;

            foreach ($data as $k => $v) {
                if (fnmatch("hash.$hash.*", $k)) {
                    yield $k;
                    yield $v;
                }
            }
        }

        public function toArray()
        {
            $data = self::$data;
            $keys = array_keys($data);

            $collection = [];

            foreach ($keys as $key) {
                Arrays::set($collection, $key, $data[$key]);
            }

            return $collection;
        }

        public function count()
        {
            return count(self::$data);
        }

        public function empty()
        {
            return count(self::$data) == 0;
        }

        public function notEmpty()
        {
            return count(self::$data) > 0;
        }

        public function toCollection()
        {
            return coll($this->toArray());
        }

        public function toJson()
        {
            return json_encode($this->toArray());
        }

        public function toSerialize()
        {
            return serialize($this->toArray());
        }

        public function getDel($k, $d = null)
        {
            $value = $this->get($k, $d);

            $this->del($k);

            return $value;
        }

        public function pull($k, $d = null)
        {
            $value = $this->get($k, $d);

            $this->del($k);

            return $value;
        }
    }
