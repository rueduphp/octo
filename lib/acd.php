<?php
    namespace Octo;

    class Acd
    {
        private $dir;
        private $tmp = ['write' => [], 'del' => []];
        private $cache = [];

        public function __construct($ns = 'core')
        {
            $this->dir = Config::get('acd.dir', '/home/acd/');

            if (!is_dir($this->dir)) {
                File::mkdir($this->dir);
            }

            $this->dir = $this->dir . DS . $ns;

            if (!is_dir($this->dir)) {
                File::mkdir($this->dir);
            }
        }

        public function __destruct()
        {
            if (!empty($this->tmp['del'])) {
                foreach ($this->tmp['del'] as $k) {
                    $this->unwrite($k);
                }
            }

            if (!empty($this->tmp['write'])) {
                foreach ($this->tmp['write'] as $infos) {
                    $k      = array_shift($infos);
                    $v      = array_shift($infos);
                    $expire = array_shift($infos);

                    $this->write($k, $v, $expire);
                }
            }
        }

        public function set($k, $v, $expire = null)
        {
            $this->tmp['write'][] = func_get_args();
            $this->cache[$k] = $v;

            return $this;
        }

        public function write($k, $v, $expire = null)
        {
            $file = $this->dir . DS . $k . '.fmr';
            $ageFile = $this->dir . DS . $k . '.age';

            if (File::exists($file) && File::exists($ageFile)) {
                File::delete($file);
                File::delete($ageFile);
            }

            File::put($file, serialize($v));

            $expire = is_null($expire) ? strtotime('+10 year') : time() + $expire;
            File::put($ageFile, $expire);

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

        public function setExpireAt($k, $v, $expire)
        {
            return $this->set($k, $v, $expire);
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
            $cached = isAke($this->cache, $k, null);

            if ($cached) {
                return $cached;
            }

            $file       = $this->dir . DS . $k . '.fmr';
            $ageFile    = $this->dir . DS . $k . '.age';

            if (File::exists($file) && File::exists($ageFile)) {
                $age = File::read($ageFile);

                if ($age >= time()) {
                    return unserialize(File::read($file));
                } else {
                    File::delete($file);
                    File::delete($ageFile);
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

        public function has($k)
        {
            $cached = isAke($this->cache, $k, null);

            if ($cached) {
                return true;
            }

            $file       = $this->dir . DS . $k . '.fmr';
            $ageFile    = $this->dir . DS . $k . '.age';

            if (File::exists($file) && File::exists($ageFile) ) {
                $age = File::read($ageFile);

                if ($age >= time()) {
                    return true;
                } else {
                    File::delete($file);
                    File::delete($ageFile);
                }
            }

            return false;
        }

        public function whenExpire($k)
        {
            $ageFile    = $this->dir . DS . $k . '.age';
            $file       = $this->dir . DS . $k . '.fmr';

            if (file_exists($ageFile)) {
                $age = File::read($ageFile);

                if ($age >= time()) {
                    return $age;
                } else {
                    File::delete($file);
                    File::delete($ageFile);
                }
            }

            return null;
        }

        public function age($k)
        {
            $file = $this->dir . DS . $k . '.age';

            if (File::exists($file)) {
                return File::read($file);
            }

            return null;
        }

        public function delete($k)
        {
            unset($this->cache[$k]);

            $this->tmp['del'][] = $k;

            return $this;
        }

        public function unwrite($k)
        {
            $file       = $this->dir . DS . $k . '.fmr';
            $ageFile    = $this->dir . DS . $k . '.age';

            if (file_exists($file)) {
                File::delete($file);
                File::delete($ageFile);

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
            $keys = glob($this->dir . DS . $pattern . '.fmr', GLOB_NOSORT);

            foreach ($keys as $key) {
                $k = str_replace([$this->dir . DS, '.fmr'], '', $key);

                yield $k;
            }
        }

        public function flush($pattern = '*')
        {
            $keys = glob($this->dir . DS . $pattern . '.fmr', GLOB_NOSORT);

            $affected = 0;

            foreach ($keys as $key) {
                File::delete($key);
                File::delete(str_replace('.fmr', '.age', $key));
                $affected++;
            }

            return $affected;
        }

        public function clean($pattern = '*')
        {
            $keys = glob($this->dir . DS . $pattern . '.fmr', GLOB_NOSORT);

            $affected = 0;

            foreach ($keys as $key) {
                $age = File::read(str_replace('.fmr', '.age', $key));

                if ($age < time()) {
                    File::delete($key);
                    File::delete(str_replace('.fmr', '.age', $key));
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

        public function hset($hash, $key, $value)
        {
            $key = "hash.$hash.$key";

            return $this->set($key, $value);
        }

        public function hsetnx($hash, $key, $value)
        {
            if (!$this->hexists($hash, $key)) {
                $this->hset($hash, $key, $value);

                return true;
            }

            return false;
        }

        public function hget($hash, $key, $default = null)
        {
            $key = "hash.$hash.$key";

            return $this->get($key, $default);
        }

        public function hstrlen($hash, $key)
        {
            if ($value = $this->hget($hash, $key)) {
                return strlen($value);
            }

            return 0;
        }

        public function hgetOr($hash, $k, callable $c)
        {
            if ($this->hexists($hash, $k)) {
                return $this->hget($hash, $k);
            }

            $res = $c();

            $this->hset($hash, $k, $res);

            return $res;
        }

        public function hwatch($hash, $k, callable $exists = null, callable $notExists = null)
        {
            if ($this->hexists($hash, $k)) {
                if (is_callable($exists)) {
                    return $exists($this->hget($hash, $k));
                }
            } else {
                if (is_callable($notExists)) {
                    return $notExists();
                }
            }

            return false;
        }

        public function hReadAndDelete($hash, $key, $default = null)
        {
            if ($this->hexists($hash, $key)) {
                $value = $this->hget($hash, $key);

                $this->hdelete($hash, $key);

                return $value;
            }

            return $default;
        }

        public function hdelete($hash, $key)
        {
            $key = "hash.$hash.$key";

            return $this->delete($key);
        }

        public function hdel($hash, $key)
        {
            return $this->hdelete($hash, $key);
        }

        public function hhas($hash, $key)
        {
            $key = "hash.$hash.$key";

            return $this->has($key);
        }

        public function hexists($hash, $key)
        {
            return $this->hhas($hash, $key);
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

        public function hgetall($hash)
        {
            $keys = glob($this->dir . DS . 'hash.' . $hash . '.*.fmr', GLOB_NOSORT);

            foreach ($keys as $row) {
                $key = str_replace(['.fmr', "hash.$hash."], '', Arrays::last(explode(DS, $row)));

                yield $key;
                yield unserialize(File::read($row));
            }
        }

        public function hvals($hash)
        {
            $keys = glob($this->dir . DS . 'hash.' . $hash . '.*.fmr', GLOB_NOSORT);

            foreach ($keys as $row) {
                yield unserialize(File::read($row));
            }
        }

        public function hlen($hash)
        {
            $keys = glob($this->dir . DS . 'hash.' . $hash . '.*.fmr', GLOB_NOSORT);

            return count($keys);
        }

        public function hremove($hash)
        {
            $keys = glob($this->dir . DS . 'hash.' . $hash . '.*.fmr', GLOB_NOSORT);

            foreach ($keys as $row) {
                File::delete($row);
            }

            return true;
        }

        public function hkeys($hash)
        {
            $keys = glob($this->dir . DS . 'hash.' . $hash . '.*.fmr', GLOB_NOSORT);

            foreach ($keys as $row) {
                $key = str_replace(['.fmr', "hash.$hash."], '', Arrays::last(explode(DS, $row)));

                yield $key;
            }
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
                    $maxAge = ($maxAge * 60) + microtime(true);
                }

                $this->set($keyAge, $maxAge);
            }

            return $data;
        }
    }
