<?php
    namespace Octo;

    class Me implements \ArrayAccess
    {
        private $db;
        private static $instances = [];

        public function __construct($ns = 'core')
        {
            $this->db = db('me', $ns . '_' . sha1(forever()));
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
            $exists = $this->db->where(['key', '=', $k])->first(true);

            if ($exists) {
                $update = $exists->setValue($v)->save();
            } else {
                $new    = $this->db->create(['key' => $k, 'value' => $v])->save();
            }

            return $this;
        }

        public function get($k, $default = null)
        {
            $exists = $this->db->where(['key', '=', $k])->first();

            if ($exists) {
                return $exists['value'];
            }

            return $default;
        }

        public function has($k)
        {
            $exists = $this->db->where(['key', '=', $k])->first(true);

            return $exists ? true : false;
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
            $exists = $this->db->where(['key', '=', $k])->first(true);

            if ($exists) {
                $exists->delete();
            }

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

        public function getIp()
        {
            if (getenv("HTTP_CLIENT_IP")) {
                return getenv("HTTP_CLIENT_IP");
            } elseif (getenv("HTTP_X_FORWARDED_FOR")) {
                return getenv("HTTP_X_FORWARDED_FOR");
            } else {
                return getenv("REMOTE_ADDR");
            }

            return '192.168.1.1';
        }

        private function forever()
        {
            $key    = [];
            $key[]  = $this->getIp();
            $key[]  = isAke($_SERVER, 'HTTP_USER_AGENT', 'Thin 1.1');
            $key[]  = isAke($_SERVER, 'HTTP_ACCEPT_LANGUAGE', 'fr-FR,fr;q=0.5');

            return sha1(
                implode(
                    '',
                    $key
                )
            );
        }

        public static function __callStatic($m, $a)
        {
            $i = self::instance();

            return call_user_func_array([$i, $m], $a);
        }

        public function __call($m, $a)
        {
            if (fnmatch('get*', $m)) {
                $k = Inflector::uncamelize(substr($m, 3));

                $default = empty($a) ? null : current($a);

                return $this->get($k, $default);
            } elseif (fnmatch('set*', $m)) {
                $k = Inflector::uncamelize(substr($m, 3));

                return $this->set($k, current($a));
            } elseif (fnmatch('has*', $m)) {
                $k = Inflector::uncamelize(substr($m, 3));

                return $this->has($k);
            } elseif (fnmatch('del*', $m)) {
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

        public static function instance($ns = 'core')
        {
            $i = isAke(self::$instances, $ns, false);

            if (!$i) {
                $i = new self($ns);

                self::$instances[$ns] = $i;
            }

            return $i;
        }
    }

