<?php
    namespace Octo;

    class Cache implements CacheI, FastCacheInterface
    {
        use Notifiable;

        private $dir;
        private $id;
        private static $instances = [];

        /**
         * @param $k
         *
         * @return string
         *
         * @throws Exception
         */
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

        /**
         * @param string $ns
         * @param null $dir
         *
         * @throws Exception
         */
        public function __construct($ns = 'core', $dir = null)
        {
            $dir = is_null($dir) ? conf('dir.cache', session_save_path()) : $dir;

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
            if ('if' === $m) {
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

        /**
         * @param $k
         * @param $v
         * @param null $expire
         *
         * @return $this
         *
         * @throws Exception
         * @throws \Exception
         */
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

        /**
         * @param $k
         * @param $v
         * @param null $expire
         *
         * @return Cache
         *
         * @throws Exception
         * @throws \Exception
         */
        public function put($k, $v, $expire = null)
        {
            return $this->set($k, $v, $expire);
        }

        /**
         * @param array $values
         * @param null $e
         *
         * @return $this
         *
         * @throws Exception
         * @throws \Exception
         */
        public function setMany(array $values, $e = null)
        {
            foreach ($values as $k => $v) {
                $this->set($k, $v, $e);
            }

            return $this;
        }

        /**
         * @param array $values
         * @param null $e
         *
         * @return $this
         *
         * @throws Exception
         * @throws \Exception
         */
        public function mset(array $values, $e = null)
        {
            foreach ($values as $k => $v) {
                $this->set($k, $v, $e);
            }

            return $this;
        }

        /**
         * @param array $keys
         *
         * @return array
         *
         * @throws Exception
         * @throws \Exception
         */
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

        /**
         * @param $key
         * @param $value
         * @param null $expire
         *
         * @return bool
         *
         * @throws Exception
         * @throws \Exception
         */
        public function setnx($key, $value, $expire = null)
        {
            if (!$this->has($key)) {
                $this->set($key, $value, $expire);

                return true;
            }

            return false;
        }

        /**
         * @param $key
         * @param $value
         * @param null $expire
         *
         * @return bool
         *
         * @throws Exception
         * @throws \Exception
         */
        public function replace($key, $value, $expire = null)
        {
            if ($this->has($key)) {
                $this->set($key, $value, $expire);

                return true;
            }

            return false;
        }

        public function at($k, $v, $timestamp)
        {
            return $this->setExpireAt($k, $v, $timestamp);
        }

        /**
         * @param $k
         * @param $v
         * @param $timestamp
         *
         * @return $this
         *
         * @throws Exception
         * @throws \Exception
         */
        public function setExpireAt($k, $v, $timestamp)
        {
            $file = $this->getPath($k);

            File::delete($file);

            File::put($file, serialize($v));

            touch($file, $timestamp);

            return $this;
        }

        /**
         * @param $k
         * @param $v
         * @param $expire
         *
         * @return Cache
         *
         * @throws Exception
         * @throws \Exception
         */
        public function setExp($k, $v, $expire)
        {
            return $this->set($k, $v, $expire);
        }

        /**
         * @param $k
         * @param $v
         * @param $expire
         *
         * @return Cache
         *
         * @throws Exception
         * @throws \Exception
         */
        public function setExpire($k, $v, $expire)
        {
            return $this->set($k, $v, $expire);
        }

        /**
         * @param $k
         * @param $expire
         *
         * @return Cache
         *
         * @throws Exception
         * @throws \Exception
         */
        public function expire($k, $expire)
        {
            $v = $this->get($k);

            return $this->set($k, $v, $expire);
        }

        /**
         * @param $k
         * @param $timestamp
         *
         * @return Cache
         *
         * @throws Exception
         * @throws \Exception
         */
        public function expireAt($k, $timestamp)
        {
            $v = $this->get($k);

            return $this->setExpireAt($k, $v, $timestamp);
        }

        /**
         * @param $k
         * @param null $d
         *
         * @return mixed
         *
         * @throws Exception
         * @throws \Exception
         */
        public function get($k, $d = null)
        {
            $file = $this->getPath($k);

            if (file_exists($file)) {
                $age = filemtime($file);

                if ($age >= time()) {
                    return value(unserialize(File::read($file)));
                } else {
                    File::delete($file);
                }
            }

            return value($d);
        }

        /**
         * @param string $k
         * @param callable $c
         *
         * @return mixed
         *
         * @throws Exception
         * @throws \Exception
         * @throws \ReflectionException
         */
        public function forever(string $k, callable $c)
        {
            return $this->getOr($k, $c);
        }

        /**
         * @param $k
         * @param callable $c
         * @param null $e
         *
         * @return mixed
         *
         * @throws Exception
         * @throws \Exception
         * @throws \ReflectionException
         */
        public function getOr($k, callable $c, $e = null)
        {
            $res = $this->get($k, 'octodummy');

            if ('octodummy' === $res) {
                $this->set($k, $res = instanciator()->makeClosure($c), $e);
            }

            return $res;
        }

        /**
         * @param $k
         * @param $c
         * @param null $e
         *
         * @return mixed
         *
         * @throws Exception
         * @throws \Exception
         * @throws \ReflectionException
         */
        public function remember($k, $c, $e = null)
        {
            if (!is_callable($c)) {
                $c = function () use ($c) {return $c;};
            }

            return $this->getOr($k, $c, $e);
        }

        /**
         * @param $k
         * @param callable|null $exists
         * @param callable|null $notExists
         *
         * @return bool
         *
         * @throws Exception
         * @throws \Exception
         */
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
            $key        = $isLogged ?
                sha1(lng() . '.' . forever() . '1.' . $k) :
                sha1(lng() . '.' . forever() . '0.' . $k);

            return 'dummyget' == $v ? $this->get($key) : $this->set($key, $v, $e);
        }

        public function my($k, $v = 'dummyget', $e = null)
        {
            $user       = my('web')->getUser();
            $isLogged   = !is_null($user);
            $key        = $isLogged ? sha1(lng() . '.' . forever() . '1.' . $k) :  sha1(lng() . '.' . forever() . '0.' . $k);

            return 'dummyget' == $v ? $this->get($key) : $this->set($key, $v, $e);
        }

        /**
         * @param $k
         * @param callable $c
         * @param $a
         *
         * @return mixed
         *
         * @throws Exception
         * @throws \Exception
         *
         */
        public function aged($k, callable $c, $a)
        {
            $k = sha1($this->dir) . '.' . $k;

            return $this->until($k, $c, $a);
        }

        /**
         * @param $k
         *
         * @return bool
         *
         * @throws Exception
         * @throws \Exception
         */
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

        /**
         * @param $k
         *
         * @return bool|int|null
         *
         * @throws Exception
         * @throws \Exception
         */
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

        /**
         * @param $k
         *
         * @return bool
         *
         * @throws Exception
         * @throws \Exception
         */
        public function delete($k)
        {
            $file = $this->getPath($k);

            if (file_exists($file)) {
                File::delete($file);

                return true;
            }

            return false;
        }

        /**
         * @param $k
         *
         * @return bool
         *
         * @throws Exception
         * @throws \Exception
         */
        public function del($k)
        {
            return $this->delete($k);
        }

        /**
         * @param $k
         *
         * @return bool
         *
         * @throws Exception
         * @throws \Exception
         */
        public function remove($k)
        {
            return $this->delete($k);
        }

        /**
         * @param $k
         *
         * @return bool
         *
         * @throws Exception
         * @throws \Exception
         */
        public function forget($k)
        {
            return $this->delete($k);
        }

        /**
         * @param $k
         *
         * @return bool
         *
         * @throws Exception
         * @throws \Exception
         */
        public function destroy($k)
        {
            return $this->delete($k);
        }

        /**
         * @param $k
         * @param int $by
         *
         * @return int|mixed
         *
         * @throws Exception
         * @throws \Exception
         */
        public function incr($k, $by = 1)
        {
            $old = $this->get($k, 0);
            $new = $old + $by;

            $this->set($k, $new);

            return $new;
        }

        /**
         * @param $k
         * @param int $by
         *
         * @return int|mixed
         *
         * @throws Exception
         * @throws \Exception
         */
        public function increment($k, $by = 1)
        {
            return $this->incr($k, $by);
        }

        /**
         * @param $k
         * @param int $by
         *
         * @return int|mixed
         *
         * @throws Exception
         * @throws \Exception
         */
        public function decr($k, $by = 1)
        {
            $old = $this->get($k, 0);
            $new = $old - $by;

            $this->set($k, $new);

            return $new;
        }

        /**
         * @param $k
         * @param int $by
         *
         * @return int|mixed
         *
         * @throws Exception
         * @throws \Exception
         */
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

        /**
         * @param string $pattern
         *
         * @return int
         *
         * @throws \Exception
         */
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

        /**
         * @param $key
         * @param null $default
         *
         * @return mixed|null
         *
         * @throws Exception
         * @throws \Exception
         */
        public function readAndDelete($key, $default = null)
        {
            if ($this->has($key)) {
                $value = $this->get($key);

                $this->delete($key);

                return $value;
            }

            return $default;
        }

        /**
         * @param $keyFrom
         * @param $keyTo
         * @param null $default
         *
         * @return Cache
         *
         * @throws Exception
         * @throws \Exception
         */
        public function rename($keyFrom, $keyTo, $default = null)
        {
            $value = $this->readAndDelete($keyFrom, $default);

            return $this->set($keyTo, $value);
        }

        public function copy($keyFrom, $keyTo)
        {
            return $this->set($keyTo, $this->get($keyFrom));
        }

        /**
         * @param $key
         * @return int
         *
         * @throws Exception
         * @throws \Exception
         */
        public function getSize($key)
        {
            return strlen($this->get($key));
        }

        /**
         * @param $key
         *
         * @return int
         *
         * @throws Exception
         * @throws \Exception
         */
        public function length($key)
        {
            return strlen($this->get($key));
        }

        /**
         * @param $hash
         * @param $key
         * @param $value
         *
         * @return Cache
         *
         * @throws Exception
         * @throws \Exception
         */
        public function hset($hash, $key, $value)
        {
            $key = "hash.$hash.$key";

            return $this->set($key, $value);
        }

        /**
         * @param $hash
         * @param $key
         * @param $value
         *
         * @return bool
         *
         * @throws Exception
         * @throws \Exception
         */
        public function hsetnx($hash, $key, $value)
        {
            if (!$this->hexists($hash, $key)) {
                $this->hset($hash, $key, $value);

                return true;
            }

            return false;
        }

        /**
         * @param $hash
         * @param $key
         * @param null $default
         *
         * @return mixed
         *
         * @throws Exception
         * @throws \Exception
         */
        public function hget($hash, $key, $default = null)
        {
            $key = "hash.$hash.$key";

            return $this->get($key, $default);
        }

        /**
         * @param $hash
         * @param $key
         *
         * @return int
         *
         * @throws Exception
         * @throws \Exception
         */
        public function hstrlen($hash, $key)
        {
            if ($value = $this->hget($hash, $key)) {
                return strlen($value);
            }

            return 0;
        }

        /**
         * @param $hash
         * @param $k
         * @param callable $c
         *
         * @return mixed
         *
         * @throws Exception
         * @throws \Exception
         */
        public function hgetOr($hash, $k, callable $c)
        {
            if ($this->hexists($hash, $k)) {
                return $this->hget($hash, $k);
            }

            $res = $c();

            $this->hset($hash, $k, $res);

            return $res;
        }

        /**
         * @param $hash
         * @param $k
         * @param callable|null $exists
         * @param callable|null $notExists
         *
         * @return bool
         *
         * @throws Exception
         * @throws \Exception
         */
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

        /**
         * @param $hash
         * @param $key
         * @param null $default
         *
         * @return mixed|null
         *
         * @throws Exception
         * @throws \Exception
         */
        public function hReadAndDelete($hash, $key, $default = null)
        {
            if ($this->hexists($hash, $key)) {
                $value = $this->hget($hash, $key);

                $this->hdelete($hash, $key);

                return $value;
            }

            return $default;
        }

        /**
         * @param $hash
         * @param $key
         *
         * @return bool
         *
         * @throws Exception
         * @throws \Exception
         */
        public function hdelete($hash, $key)
        {
            $key = "hash.$hash.$key";

            return $this->delete($key);
        }

        /**
         * @param $hash
         * @param $key
         *
         * @return bool
         *
         * @throws Exception
         * @throws \Exception
         */
        public function hdel($hash, $key)
        {
            return $this->hdelete($hash, $key);
        }

        /**
         * @param $hash
         * @param $key
         *
         * @return bool
         *
         * @throws Exception
         * @throws \Exception
         */
        public function hhas($hash, $key)
        {
            $key = "hash.$hash.$key";

            return $this->has($key);
        }

        /**
         * @param $hash
         * @param $key
         *
         * @return bool
         *
         * @throws Exception
         * @throws \Exception
         */
        public function hexists($hash, $key)
        {
            return $this->hhas($hash, $key);
        }

        /**
         * @param $hash
         * @param $key
         * @param int $by
         *
         * @return int|mixed
         *
         * @throws Exception
         * @throws \Exception
         */
        public function hincr($hash, $key, $by = 1)
        {
            $old = $this->hget($hash, $key, 1);
            $new = $old + $by;

            $this->hset($hash, $key, $new);

            return $new;
        }

        /**
         * @param $hash
         * @param $key
         * @param int $by
         *
         * @return int|mixed
         *
         * @throws Exception
         * @throws \Exception
         */
        public function hdecr($hash, $key, $by = 1)
        {
            $old = $this->hget($hash, $key, 1);
            $new = $old - $by;

            $this->hset($hash, $key, $new);

            return $new;
        }

        /**
         * @param $hash
         *
         * @return \Generator
         */
        public function hgetall($hash)
        {
            $keys = $this->glob($this->dir . DS . 'hash.' . $hash . '.*.kh', GLOB_NOSORT);

            foreach ($keys as $row) {
                $key = str_replace(['.kh', "hash.$hash."], '', Arrays::last(explode(DS, $row)));

                yield [$key => unserialize(File::read($row))];
            }
        }

        /**
         * @param $hash
         *
         * @return \Generator
         */
        public function hvals($hash)
        {
            $keys = $this->glob($this->dir . DS . 'hash.' . $hash . '.*.kh', GLOB_NOSORT);

            foreach ($keys as $row) {
                yield unserialize(File::read($row));
            }
        }

        /**
         * @param $hash
         *
         * @return int
         */
        public function hlen($hash)
        {
            $keys = $this->glob($this->dir . DS . 'hash.' . $hash . '.*.kh', GLOB_NOSORT);

            return count($keys);
        }

        /**
         * @param $hash
         *
         * @return bool
         *
         * @throws \Exception
         */
        public function hremove($hash)
        {
            $keys = $this->glob($this->dir . DS . 'hash.' . $hash . '.*.kh', GLOB_NOSORT);

            foreach ($keys as $row) {
                File::delete($row);
            }

            return true;
        }

        /**
         * @param $hash
         *
         * @return \Generator
         */
        public function hkeys($hash)
        {
            $keys = $this->glob($this->dir . DS . 'hash.' . $hash . '.*.kh', GLOB_NOSORT);

            foreach ($keys as $row) {
                $key = str_replace(['.kh', "hash.$hash."], '', Arrays::last(explode(DS, $row)));

                yield $key;
            }
        }

        /**
         * @param $key
         * @param $value
         *
         * @return Cache
         *
         * @throws Exception
         * @throws \Exception
         */
        public function sadd($key, $value)
        {
            $tab = $this->get($key, []);
            $tab[] = $value;

            return $this->set($key, $tab);
        }

        /**
         * @param $key
         *
         * @return int
         *
         * @throws Exception
         * @throws \Exception
         */
        public function scard($key)
        {
            $tab = $this->get($key, []);

            return count($tab);
        }

        /**
         * @return array
         *
         * @throws Exception
         * @throws \Exception
         */
        public function sinter()
        {
            $tab = [];

            foreach (func_get_args() as $key) {
                $tab = array_intersect($tab, $this->get($key, []));
            }

            return $tab;
        }

        /**
         * @return array
         *
         * @throws Exception
         * @throws \Exception
         */
        public function sunion()
        {
            $tab = [];

            foreach (func_get_args() as $key) {
                $tab = array_merge($tab, $this->get($key, []));
            }

            return $tab;
        }

        /**
         * @return Cache
         *
         * @throws Exception
         * @throws \Exception
         */
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

        /**
         * @return Cache
         *
         * @throws Exception
         * @throws \Exception
         */
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

        /**
         * @param $hash
         * @param $key
         *
         * @return bool
         *
         * @throws Exception
         * @throws \Exception
         */
        public function sismember($hash, $key)
        {
            return in_array($key, $this->get($hash, []));
        }

        /**
         * @param $key
         *
         * @return mixed
         *
         * @throws Exception
         * @throws \Exception
         */
        public function smembers($key)
        {
            return $this->get($key, []);
        }

        /**
         * @param $hash
         * @param $key
         *
         * @return bool
         *
         * @throws Exception
         * @throws \Exception
         */
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

        /**
         * @param $from
         * @param $to
         * @param $key
         *
         * @return bool
         *
         * @throws Exception
         * @throws \Exception
         */
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

        /**
         * @param $k
         * @param callable $c
         * @param null $maxAge
         * @param array $args
         *
         * @return mixed
         *
         * @throws Exception
         * @throws \Exception
         */
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

        /**
         * @param $key
         * @param string $val
         *
         * @return mixed|Cache|string
         *
         * @throws Exception
         * @throws \Exception
         */
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

        /**
         * @param $k
         * @param $v
         * @param $e
         *
         * @return $this|Cache
         *
         * @throws Exception
         * @throws \Exception
         */
        public function add($k, $v, $e)
        {
            if (!$this->has($k)) {
                return $this->set($k, $v, $e);
            }

            return $this;
        }

        /**
         * @param $k
         * @param $v
         * @param null $expire
         *
         * @return $this
         *
         * @throws Exception
         * @throws \Exception
         */
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

        /**
         * @param $k
         *
         * @return bool
         *
         * @throws Exception
         */
        public function hasNow($k)
        {
            return file_exists($this->getPath($k));
        }

        /**
         * @param $k
         * @param null $d
         *
         * @return mixed|null
         *
         * @throws Exception
         */
        public function getNow($k, $d = null)
        {
            if (file_exists($file = $this->getPath($k))) {
                return unserialize(File::read($file));
            }

            return $d;
        }

        /**
         * @param $k
         *
         * @return bool
         *
         * @throws Exception
         * @throws \Exception
         */
        public function delNow($k)
        {
            if (file_exists($file = $this->getPath($k))) {
                File::delete($file);

                return true;
            }

            return false;
        }

        /**
         * @param $k
         *
         * @return bool|int
         *
         * @throws Exception
         */
        public function ageNow($k)
        {
            if (file_exists($file = $this->getPath($k))) {
                return filemtime($file);
            }

            return time();
        }

        /**
         * @param $k
         * @param null $d
         *
         * @return mixed
         *
         * @throws Exception
         * @throws \Exception
         */
        public function getDel($k, $d = null)
        {
            $value = $this->get($k, $d);

            $this->delete($k);

            return $value;
        }

        /**
         * @param $k
         * @param null $d
         *
         * @return mixed|null
         *
         * @throws Exception
         * @throws \Exception
         */
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

        /**
         * @return bool|string
         *
         * @throws Exception
         * @throws \Exception
         */
        public function end()
        {
            if ($k = Registry::get('cache.buffer.' . $this->id)) {
                $value = ob_get_clean();

                $this->set($k, $value, $this->getTtl());

                return $value;
            }

            return false;
        }

        /**
         * @param null $e
         *
         * @return Cache
         */
        public function ttl($e = null): self
        {
            if ($e) {
                Registry::set('cache.ttl.' . $this->id, $e);

                return $this;
            }

            return Registry::get('cache.ttl.' . $this->id, $e);
        }

        /**
         * @param null $e
         *
         * @return null
         */
        public function getTtl($e = null)
        {
            return $e ? $e : Registry::get('cache.ttl.' . $this->id, $e);
        }
    }
