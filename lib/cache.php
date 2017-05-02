<?php
    namespace Octo;

    class Cache implements CacheI
    {
        use Notifiable;

        private $dir;
        private $id;
        private static $instances = [];

        private function getPath($k)
        {
            $dir    = $this->dir;
            $hash   = sha1($k);

            $one    = substr($hash, 0, 2);
            $two    = substr($hash, 2, 2);
            $three  = substr($hash, 4, 2);

            $dir .= DS . $one;

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $dir .= DS . $two;

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $dir .= DS . $three;

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            return $dir . DS . $k . '.kh';
        }

        public function infos($dir = null, $topLevel = true, $recursion = false)
        {
            static $fileData = [];

            $dir = empty($dir) ? $this->dir : $dir;

            $relativePath = $dir;

            if ($fp = @opendir($dir)) {
                if ($recursion === false) {
                    $fileData   = [];
                    $dir        = rtrim(realpath($dir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                }

                while (false !== ($file = readdir($fp))) {
                    if (is_dir($dir . $file) && $file[0] !== '.' && $topLevel === false) {
                        $this->infos($dir . $file . DIRECTORY_SEPARATOR, $topLevel, true);
                    } elseif ($file[0] !== '.') {
                        $fileData[$file]                  = $this->infos($dir . $file);
                        $fileData[$file]['relative_path'] = $relativePath;
                    }
                }

                closedir($fp);

                return $fileData;
            }

            return false;
        }

        public function pull($key, $default = null)
        {
            $value = $this->get($key, $default);

            $this->forget($key);

            return $value;
        }

        public function __construct($ns = 'core', $dir = null)
        {
            $dir = is_null($dir) ? Config::get('dir.cache', session_save_path()) : $dir;

            $this->dir = $dir . DS . $ns;

            if (!is_dir($this->dir)) {
                File::mkdir($this->dir);
            }

            $this->id = sha1($ns);
        }

        public static function instance($ns = 'core', $dir = null)
        {
            $key = sha1(serialize(func_get_args()));

            $instance = isAke(static::$instances, $key, null);

            if (!$instance) {
                $instance = new static($ns, $dir);

                static::$instances[$key] = $instance;
            }

            return $instance;
        }

        public function __call($m, $a)
        {
            if ('if' == $m) {
                return call_user_func_array([$this, 'cacheIf'], $a);
            }
        }

        public function cacheIf($k, $condition, $value, $expire = null)
        {
            $condition  = value($condition);
            $value      = value($value);

            if ($condition) {
                $this->set($k, $value, $expire);
            }

            return $value;
        }

        public function setDirectory($dir)
        {
            if (realpath($dir)) {
                $this->dir = realpath($dir);
            }

            return $this;
        }

        public function getDirectory()
        {
            return $this->dir;
        }

        public function set($k, $v, $expire = null)
        {
            $file = $this->getPath($k);

            File::delete($file);

            $v = value($v);

            File::put($file, serialize($v));

            $expire = is_null($expire) ? strtotime('+10 year') : time() + $expire;

            @touch($file, $expire);

            return $this;
        }

        public function put($k, $v, $expire = null)
        {
            return $this->set($k, $v, $expire);
        }

        public function setMany(array $values, $e = null)
        {
            foreach ($values as $k => $v) {
                $this->set($k, $v, $e);
            }

            return $this;
        }

        public function mset(array $values, $e = null)
        {
            foreach ($values as $k => $v) {
                $this->set($k, $v, $e);
            }

            return $this;
        }

        public function many(array $keys)
        {
            $return = [];

            foreach ($keys as $key) {
                $return[$key] = $this->get($key);
            }

            return $return;
        }

        public function mget(array $keys)
        {
            $return = [];

            foreach ($keys as $key) {
                $return[$key] = $this->get($key);
            }

            return $return;
        }

        public function setnx($key, $value, $expire = null)
        {
            if (!$this->has($key)) {
                $this->set($key, $value, $expire);

                return true;
            }

            return false;
        }

        public function replace($key, $value, $expire = null)
        {
            if ($this->has($key)) {
                $this->set($key, $value, $expire);

                return true;
            }

            return false;
        }

        public function setExpireAt($k, $v, $timestamp)
        {
            $file = $this->getPath($k);

            File::delete($file);

            File::put($file, serialize($v));

            touch($file, $timestamp);

            return $this;
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
            $file = $this->getPath($k);

            if (file_exists($file)) {
                $age = filemtime($file);

                if ($age >= time()) {
                    return File::value(unserialize(File::read($file)));
                } else {
                    File::delete($file);
                }
            }

            return value($d);
        }

        public function forever($k, $v)
        {
            return $this->set($k, $v);
        }

        public function getOr($k, callable $c, $e = null)
        {
            $res = $this->get($k, 'octodummy');

            if ('octodummy' == $res) {
                $this->set($k, $res = $c(), $e);
            }

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

        public function my($k, $v = 'dummyget', $e = null)
        {
            $user       = my('web')->getUser();
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
            $file = $this->getPath($k);

            if (file_exists($file)) {
                $age = filemtime($file);

                if ($age >= time()) {
                    return true;
                } else {
                    File::delete($file);
                }
            }

            return false;
        }

        public function age($k)
        {
            $file = $this->getPath($k);

            if (file_exists($file)) {
                $age = filemtime($file);

                if ($age >= time()) {
                    return $age;
                } else {
                    File::delete($file);
                }
            }

            return null;
        }

        public function delete($k)
        {
            $file = $this->getPath($k);

            if (file_exists($file)) {
                File::delete($file);

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

        public function destroy($k)
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
            $keys = $this->glob($this->dir . DS . $pattern . '.kh', GLOB_NOSORT);

            foreach ($keys as $key) {
                $key    = Arrays::last(explode(DS, $key));
                $k      = str_replace(['.kh'], '', $key);

                yield $k;
            }
        }

        public function glob($pattern, $flags = 0)
        {
            $files = glob($pattern, $flags);

            foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
                $files = array_merge($files, $this->glob($dir . DS . basename($pattern), $flags));
            }

            return $files;
        }

        public function flush($pattern = '*')
        {
            $keys = $this->glob($this->dir . DS . $pattern . '.kh', GLOB_NOSORT);

            $affected = 0;

            foreach ($keys as $key) {
                File::delete($key);
                $affected++;
            }

            return $affected;
        }

        public function clean($pattern = '*')
        {
            $keys = $this->glob($this->dir . DS . $pattern . '.kh', GLOB_NOSORT);

            $affected = 0;

            foreach ($keys as $key) {
                $age = filemtime($key);

                if ($age < time()) {
                    File::delete($key);

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
            $keys = $this->glob($this->dir . DS . 'hash.' . $hash . '.*.kh', GLOB_NOSORT);

            foreach ($keys as $row) {
                $key = str_replace(['.kh', "hash.$hash."], '', Arrays::last(explode(DS, $row)));

                yield $key;
                yield unserialize(File::read($row));
            }
        }

        public function hvals($hash)
        {
            $keys = $this->glob($this->dir . DS . 'hash.' . $hash . '.*.kh', GLOB_NOSORT);

            foreach ($keys as $row) {
                yield unserialize(File::read($row));
            }
        }

        public function hlen($hash)
        {
            $keys = $this->glob($this->dir . DS . 'hash.' . $hash . '.*.kh', GLOB_NOSORT);

            return count($keys);
        }

        public function hremove($hash)
        {
            $keys = $this->glob($this->dir . DS . 'hash.' . $hash . '.*.kh', GLOB_NOSORT);

            foreach ($keys as $row) {
                File::delete($row);
            }

            return true;
        }

        public function hkeys($hash)
        {
            $keys = $this->glob($this->dir . DS . 'hash.' . $hash . '.*.kh', GLOB_NOSORT);

            foreach ($keys as $row) {
                $key = str_replace(['.kh', "hash.$hash."], '', Arrays::last(explode(DS, $row)));

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

        public function flash($key, $val = 'octodummy')
        {
            $key = "flash_{$key}";

            if ($val != 'octodummy') {
                $this->set($key, $val);
            } else {
                $val = $this->get($key);
                $this->delete($key);
            }

            return $val != 'octodummy' ? $this : $val;
        }

        public function add($k, $v, $e)
        {
            if (!$this->has($key)) {
                return $this->set($k, $v, $e);
            }

            return $this;
        }

        public function setNow($k, $v, $expire = null)
        {
            $file = $this->getPath($k);

            File::delete($k);

            $v = File::value($v);

            File::put($file, serialize($v));

            $expire = is_null($expire) ? time() : time() + $expire;

            @touch($file, $expire);

            return $this;
        }

        public function hasNow($k)
        {
            return file_exists($this->getPath($k));
        }

        public function getNow($k, $d = null)
        {
            if (file_exists($file = $this->getPath($k))) {
                return unserialize(File::read($file));
            }

            return $d;
        }

        public function delNow($k)
        {
            if (file_exists($file = $this->getPath($k))) {
                File::delete($file);

                return true;
            }

            return false;
        }

        public function ageNow($k)
        {
            if (file_exists($file = $this->getPath($k))) {
                return filemtime($file);
            }

            return time();
        }

        public function getDel($k, $d = null)
        {
            $value = $this->get($k, $d);

            $this->delete($k);

            return $value;
        }

        public function start($k, $d = null)
        {
            if (!$this->has($k)) {
                Registry::set('cache.buffer.' . $this->id, $k);
                ob_start();

                return $d;
            }

            Registry::delete('cache.buffer.' . $this->id);

            return $this->get($k);
        }

        public function end()
        {
            if ($k = Registry::get('cache.buffer.' . $this->id)) {
                $value = ob_get_clean();

                $this->set($k, $value, $this->getTtl());

                return $value;
            }

            return false;
        }

        public function ttl($e = null)
        {
            if ($e) {
                Registry::set('cache.ttl.' . $this->id, $e);

                return $this;
            }

            return Registry::get('cache.ttl.' . $this->id, $e);
        }

        public function getTtl($e = null)
        {
            return $e ? $e : Registry::get('cache.ttl.' . $this->id, $e);
        }
    }
