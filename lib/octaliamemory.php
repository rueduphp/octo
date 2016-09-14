<?php
    namespace Octo;

    class OctaliaMemory
    {
        private $dir;

        private function getPath($k)
        {
            return $this->dir . '.' . $k;
        }

        public function __construct($ns = null, $dir = null)
        {
            $ns     = empty($ns)    ? 'core'    : $ns;
            $dir    = empty($dir)   ? 'memory'  : $dir;

            $this->dir = $dir . '.' . $ns;
        }

        public function setDirectory($dir)
        {
            $this->dir = $dir;

            return $this;
        }

        public function getDirectory()
        {
            return $this->dir;
        }

        protected function _read($k)
        {
            $data = Registry::get('memory.data', []);

            return isAke($data, $k, null);
        }

        protected function _put($k, $v)
        {
            $data = Registry::get('memory.data', []);

            $data[$k] = $v;

            Registry::set('memory.data', $data);

            return $this;
        }

        protected function _delete($k)
        {
            $data = Registry::get('memory.data', []);

            if ($this->_has($k)) {
                unset($data[$b]);

                Registry::set('memory.data', $data);

                return true;
            }

            return false;
        }

        protected function _has($k)
        {
            $data = Registry::get('memory.data', []);

            return 'octodummy' != isAke($data, $k, 'octodummy');

        }

        public function set($k, $v, $expire = null)
        {
            $file = $this->getPath($k);

            $this->_delete($file);

            $this->_put($file, $v);

            $expire = is_null($expire) ? strtotime('+10 year') : time() + $expire;

            $file = $this->getPath($k . '.age');

            $this->_delete($file);

            $this->_put($file, $expire);

            return $this;
        }

        public function setnx($key, $value)
        {
            if (!$this->has($key)) {
                $this->set($key, $value);

                return true;
            }

            return false;
        }

        public function setExpireAt($k, $v, $timestamp)
        {
            $file = $this->getPath($k);

            $this->_delete($file);

            $this->_put($file, $v);

            $file = $this->getPath($k . '.age');

            $this->_delete($file);

            $this->_put($file, $timestamp);

            return $this;
        }

        public function add($k, $v, $expire = null)
        {
            return $this->set($k, $v, $expire);
        }

        public function setExp($k, $v, $expire)
        {
            return $this->set($k, $v, $expire);
        }

        public function setExpire($k, $v, $expire)
        {
            return $this->set($k, $v, $expire);
        }

        public function expire($k, $expire)
        {
            $v = $this->get($k);

            return $this->set($k, $v, $expire);
        }

        public function expireAt($k, $timestamp)
        {
            $v = $this->get($k);

            return $this->set($k, $v, $timestamp);
        }

        public function get($k, $d = null)
        {
            $file       = $this->getPath($k);
            $agefile    = $this->getPath($k . '.age');

            if ($this->_has($file)) {
                $age = $this->_read($agefile);

                if ($age >= time()) {
                    return $this->_read($file);
                } else {
                    $this->_delete($file);
                    $this->_delete($agefile);
                }
            }

            return $d;
        }

        public function has($k, $d = false)
        {
            $file       = $this->getPath($k);
            $agefile    = $this->getPath($k . '.age');

            if ($this->_has($file)) {
                $age = $this->_read($agefile);

                if ($age >= time()) {
                    return true;
                } else {
                    $this->_delete($file);
                    $this->_delete($agefile);
                }
            }

            return $d;
        }

        public function getOr($k, callable $c, $e = null)
        {
            if ($this->has($k)) {
                return $this->get($k);
            }

            $res = $c();

            $this->set($k, $res, $e);

            return $res;
        }

        public function remember($k, $c, $e = null)
        {
            if (!is_callable($c)) {
                $c = function () use ($c) {return $c;};
            }

            return $this->getOr($k, $c, $e);
        }

        public function watch($k, callable $exists = null, callable $notExists = null)
        {
            if ($this->has($k)) {
                if (is_callable($exists)) {
                    return $exists($this->get($k));
                }
            } else {
                if (is_callable($notExists)) {
                    return $notExists();
                }
            }

            return false;
        }

        public function session($k, $v = 'dummyget', $e = null)
        {
            $user       = session('web')->getUser();
            $isLogged   = !is_null($user);
            $key        = $isLogged ? sha1(lng() . '.' . forever() . '1.' . $k) :  sha1(lng() . '.' . forever() . '0.' . $k);

            return 'dummyget' == $v ? $this->get($key) : $this->set($key, $v, $e);
        }

        public function aged($k, callable $c, $a)
        {
            $k = sha1($this->dir) . '.' . $k;

            return $this->until($k, $c, $a);
        }

        public function age($k)
        {
            $file = $this->getPath($k);
            $agefile = $this->getPath($k . '.age');

            if ($this->_has($file)) {
                $age = $this->_read($agefile);

                if ($age >= time()) {
                    return $age;
                } else {
                    $this->_delete($file);
                    $this->_delete($agefile);
                }
            }

            return null;
        }

        public function delete($k)
        {
            $file = $this->getPath($k);
            $agefile = $this->getPath($k . '.age');

            if ($this->_has($file)) {
                $this->_delete($file);
                $this->_delete($agefile);

                return true;
            }

            return false;
        }

        public function del($k)
        {
            return $this->delete($k);
        }

        public function remove($k)
        {
            return $this->delete($k);
        }

        public function forget($k)
        {
            return $this->delete($k);
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

        public function keys($pattern = '*')
        {
            $data = Registry::get('memory.data', []);

            $keys = [];

            foreach ($data as $k => $v) {
                if (fnmatch($this->dir . '.' . $pattern, $k)) {
                    $keys[] = str_replace($this->dir . '.', '', $k);
                }
            }

            foreach ($keys as $key) {
                yield $key;
            }
        }

        public function flush($pattern = '*')
        {
            $affected = 0;

            $data = Registry::get('memory.data', []);

            $keys = [];

            foreach ($data as $k => $v) {
                if (fnmatch($this->dir . '.' . $pattern, $k)) {
                    unset($data[$k]);
                    $affected++;
                }
            }

            if (0 < $affected) {
                Registry::set('memory.data', $data);
            }

            return $affected;
        }

        public function clean($pattern = '*')
        {
            $data = Registry::get('memory.data', []);

            $keys = [];

            foreach ($data as $k => $v) {
                if (fnmatch($this->dir . '.' . $pattern, $k)) {
                    $keys[] = str_replace($this->dir . '.', '', $k);
                }
            }

            $affected = 0;

            foreach ($keys as $key) {
                $agefile = $ley . '.age';
                $age = $this->_read($agefile);

                if ($age < time()) {
                    $this->_delete($key);
                    $this->_delete($agefile);

                    $affected++;
                }
            }

            return $affected;
        }

        public function readAndDelete($key, $default = null)
        {
            if ($this->has($key)) {
                $value = $this->get($key);

                $this->delete($key);

                return $value;
            }

            return $default;
        }

        public function rename($keyFrom, $keyTo, $default = null)
        {
            $value = $this->readAndDelete($keyFrom, $default);

            return $this->set($keyTo, $value);
        }

        public function copy($keyFrom, $keyTo)
        {
            return $this->set($keyTo, $this->get($keyFrom));
        }

        public function getSize($key)
        {
            return strlen($this->get($key));
        }

        public function length($key)
        {
            return strlen($this->get($key));
        }

        public function sadd($key, $value)
        {
            $tab = $this->get($key, []);
            $tab[] = $value;

            return $this->set($key, $tab);
        }

        public function scard($key)
        {
            $tab = $this->get($key, []);

            return count($tab);
        }

        public function sinter()
        {
            $tab = [];

            foreach (func_get_args() as $key) {
                $tab = array_intersect($tab, $this->get($key, []));
            }

            return $tab;
        }

        public function sunion()
        {
            $tab = [];

            foreach (func_get_args() as $key) {
                $tab = array_merge($tab, $this->get($key, []));
            }

            return $tab;
        }

        public function sinterstore()
        {
            $args = func_get_args();

            $destination = array_shift($args);

            $tab = [];

            foreach ($args as $key) {
                $tab = array_intersect($tab, $this->get($key, []));
            }

            return $this->set($destination, $tab);
        }

        public function sunionstore()
        {
            $args = func_get_args();

            $destination = array_shift($args);

            $tab = [];

            foreach ($args as $key) {
                $tab = array_merge($tab, $this->get($key, []));
            }

            return $this->set($destination, $tab);
        }

        public function sismember($hash, $key)
        {
            return in_array($key, $this->get($hash, []));
        }

        public function smembers($key)
        {
            return $this->get($key, []);
        }

        public function srem($hash, $key)
        {
            $tab = $this->get($hash, []);

            $new = [];

            $exists = false;

            foreach ($tab as $row) {
                if ($row != $key) {
                    $new[] = $row;
                } else {
                    $exists = true;
                }
            }

            if ($exists) {
                $this->set($hash, $new);

                return true;
            }

            return false;
        }

        public function smove($from, $to, $key)
        {
            if ($this->sismember($from, $key)) {
                $this->srem($from, $key);

                if (!$this->sismember($to, $key)) {
                    $this->sadd($to, $key);
                }

                return true;
            }

            return false;
        }

        public function until($k, callable $c, $maxAge = null, $args = [])
        {
            $keyAge = $k . '.maxage';
            $v      = $this->get($k);

            if ($v) {
                if (is_null($maxAge)) {
                    return $v;
                }

                $age = $this->get($keyAge);

                if (!$age) {
                    $age = $maxAge - 1;
                }

                if ($age >= $maxAge) {
                    return $v;
                } else {
                    $this->delete($k);
                    $this->delete($keyAge);
                }
            }

            $data = call_user_func_array($c, $args);

            $this->set($k, $data);

            if (!is_null($maxAge)) {
                if ($maxAge < 1000000000) {
                    $maxAge = ($maxAge * 60) + time();
                }

                $this->set($keyAge, $maxAge);
            }

            return $data;
        }
    }
