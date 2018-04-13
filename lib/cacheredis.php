<?php
    namespace Octo;

    class Cacheredis implements \ArrayAccess, FastCacheInterface
    {
        use Notifiable;

        private $dir;
        private $id;
        private static $instances = [];

        private function getPath(string $k)
        {
            return $this->dir . '.' . $k;
        }

        public function pull(string $key, $default = null)
        {
            $value = $this->get($key, $default);

            $this->forget($key);

            return $value;
        }

        public function __construct(string $ns = 'core')
        {
            $this->dir  = $ns;
            $this->id   = sha1('redis' . $ns);
        }

        public static function instance(string $ns = 'core')
        {
            $key        = sha1(serialize(func_get_args()));
            $instance   = isAke(static::$instances, $key, null);

            if (!$instance) {
                $instance = new static($ns);

                static::$instances[$key] = $instance;
            }

            return $instance;
        }

        public function __call(string $m, $a)
        {
            if ('if' === $m) {
                return call_user_func_array([$this, 'cacheIf'], $a);
            }
        }

        public function cacheIf(string $k, $condition, $value, $expire = null)
        {
            $condition  = value($condition);
            $value      = value($value);

            if ($condition) {
                $this->set($k, $value, $expire);
            }

            return $value;
        }

        public function setDirectory(string $dir)
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

        public function set(string $k, $v, $expire = null)
        {
            $file = $this->getPath($k);

            $v = value($v);

            redis()->set($file . '.u', time());

            if (!$this->has($k)) {
                redis()->set($file . '.c', time());
            }

            redis()->set($file, serialize($v));

            if ($expire) {
                redis()->expire($file, time() + $expire);
                redis()->expire($file . '.c', time() + $expire);
                redis()->expire($file . '.u', time() + $expire);
            }

            return $this;
        }

        public function put(string $k, $v, $expire = null)
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

        public function many(array $keys)
        {
            $return = [];

            foreach ($keys as $key) {
                $return[$key] = $this->get($key);
            }

            return $return;
        }

        public function setnx(string $key, $value, $expire = null)
        {
            if (!$this->has($key)) {
                $this->set($key, $value, $expire);

                return true;
            }

            return false;
        }

        public function setExpireAt(string $k, $v, $timestamp)
        {
            $file = $this->getPath($k);
            redis()->setex($file, serialize($v), $timestamp - time());

            return $this;
        }

        public function setExp(string $k, $v, $expire)
        {
            return $this->set($k, $v, $expire);
        }

        public function setExpire(string $k, $v, $expire)
        {
            return $this->set($k, $v, $expire);
        }

        public function expire(string $k, $expire)
        {
            $v = $this->get($k);

            return $this->set($k, $v, $expire);
        }

        public function expireAt(string $k, $timestamp)
        {
            $v = $this->get($k);

            return $this->set($k, $v, $timestamp);
        }

        public function get(string $k, $d = null)
        {
            $file = $this->getPath($k);

            if ($this->has($k)) {
                return value(unserialize(redis()->get($file)));
            }

            return value($d);
        }

        /**
         * @param string $k
         * @param callable $c
         *
         * @return mixed
         *
         * @throws \ReflectionException
         */
        public function forever(string $k, callable $c)
        {
            return $this->getOr($k, $c);
        }

        /**
         * @param string $k
         * @param callable $c
         * @param null $e
         *
         * @return mixed|null
         *
         * @throws \ReflectionException
         */
        public function getOr(string $k, callable $c, $e = null)
        {
            $res = $this->get($k, 'octodummy');

            if ('octodummy' === $res) {
                $this->set($k, $res = callCallable($c), $e);
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
         * @throws \ReflectionException
         */
        public function remember(string $k, $c, $e = null)
        {
            if (!is_callable($c)) {
                $c = function () use ($c) {return $c;};
            }

            return $this->getOr($k, $c, $e);
        }

        /**
         * @param string $k
         * @param callable|null $exists
         * @param callable|null $notExists
         *
         * @return bool|mixed|null
         *
         * @throws \ReflectionException
         */
        public function watch(string $k, ?callable $exists = null, ?callable $notExists = null)
        {
            if ($this->has($k)) {
                if (is_callable($exists)) {
                    return callCallable($exists, $this->get($k));
                }
            } else {
                if (is_callable($notExists)) {
                    return callCallable($notExists);
                }
            }

            return false;
        }

        public function session(string $k, $v = 'dummyget', $e = null)
        {
            $user       = session('web')->getUser();
            $isLogged   = !is_null($user);
            $key        = $isLogged ?
                sha1(lng() . '.' . forever() . '1.' . $k) :
                sha1(lng() . '.' . forever() . '0.' . $k);

            return 'dummyget' === $v ? $this->get($key) : $this->set($key, $v, $e);
        }

        public function my(string $k, $v = 'dummyget', $e = null)
        {
            $user       = my('web')->getUser();
            $isLogged   = !is_null($user);
            $key        = $isLogged ? sha1(lng() . '.' . forever() . '1.' . $k) :  sha1(lng() . '.' . forever() . '0.' . $k);

            return 'dummyget' === $v ? $this->get($key) : $this->set($key, $v, $e);
        }

        public function aged(string $k, callable $c, $a)
        {
            $k = sha1($this->dir) . '.' . $k;

            return $this->until($k, $c, $a);
        }

        /**
         * @return array
         */
        public function all(): array
        {
            $collection = [];

            foreach($this->keys() as $key) {
                $collection[$key] = $this->get($key);
            }

            return $collection;
        }

        /**
         * @param string $k
         *
         * @return bool
         */
        public function has(string $k)
        {
            $file = $this->getPath($k);

            return 0 !== redis()->exists($file);
        }

        public function age(string $key)
        {
            if ($this->has($key)) {
                $file = $this->getPath($key);

                return redis()->get($file . '.u');
            }

            return null;
        }

        public function delete(string $key)
        {
            if ($this->has($key)) {
                $file = $this->getPath($key);

                redis()->del($file);
                redis()->del($file . '.c');
                redis()->del($file . '.u');

                return true;
            }

            return false;
        }

        public function del(string $key)
        {
            return $this->delete($key);
        }

        public function remove(string $key)
        {
            return $this->delete($key);
        }

        public function forget(string $key)
        {
            return $this->delete($key);
        }

        public function destroy(string $key)
        {
            return $this->delete($key);
        }

        public function incr(string $key, $by = 1)
        {
            $old = $this->get($key, 0);
            $new = $old + $by;

            $this->set($key, $new);

            return $new;
        }

        public function increment(string $key, $by = 1)
        {
            return $this->incr($key, $by);
        }

        public function decr(string $key, $by = 1)
        {
            $old = $this->get($key, 0);
            $new = $old - $by;

            $this->set($key, $new);

            return $new;
        }

        public function decrement(string $key, $by = 1)
        {
            return $this->decr($key, $by);
        }

        /**
         * @param string $pattern
         * @return \Generator
         */
        public function keys(string $pattern = '*')
        {
            $keys = redis()->keys($this->dir . '.' . $pattern);

            foreach ($keys as $key) {
                if (!fnmatch('*.c', $key) && !fnmatch('*.u', $key)) {
                    $key = str_replace('core.' . $this->dir . '.', '', $key);

                    yield $key;
                }
            }
        }

        /**
         * @param string $pattern
         * @return int
         *
         * @throws \Exception
         */
        public function flush($pattern = '*')
        {
            $motor  = redis()->pipeline();
            $keys   = redis()->keys($this->dir . '.' . $pattern);

            $affected = 0;

            foreach ($keys as $key) {
                $motor->del($key);
                $affected++;
            }

            $motor->execute();

            return $affected;
        }

        /**
         * @param string $key
         * @param null $default
         * @return mixed|null
         */
        public function getDel(string $key, $default = null)
        {
            if ($this->has($key)) {
                $value = $this->get($key);

                $this->delete($key);

                return value($value);
            }

            return value($default);
        }

        public function readAndDelete(string $key, $default = null)
        {
            return $this->getDel($key, $default);
        }

        public function rename($keyFrom, $keyTo, $default = null)
        {
            $value = $this->getDel($keyFrom, $default);

            return $this->set($keyTo, $value);
        }

        public function copy($keyFrom, $keyTo)
        {
            return $this->set($keyTo, $this->get($keyFrom));
        }

        public function getSize(string $key)
        {
            return $this->has($key) ? strlen($this->get($key)) : 0;
        }

        public function length(string $key)
        {
            return $this->has($key) ? strlen($this->get($key)) : 0;
        }

        public function hset(string $hash, string $key, $value)
        {
            $key = "hash.$hash.$key";

            return $this->set($key, $value);
        }

        public function hsetnx(string $hash, string $key, $value)
        {
            if (!$this->hexists($hash, $key)) {
                $this->hset($hash, $key, $value);

                return true;
            }

            return false;
        }

        public function hget(string $hash, string $key, $default = null)
        {
            $key = "hash.$hash.$key";

            return $this->get($key, $default);
        }

        public function hstrlen(string $hash, string $key)
        {
            if ($value = $this->hget($hash, $key)) {
                return strlen($value);
            }

            return 0;
        }

        public function hgetOr(string $hash, string $key, callable $c)
        {
            if ($this->hexists($hash, $key)) {
                return $this->hget($hash, $key);
            }

            $res = $c();

            $this->hset($hash, $key, $res);

            return $res;
        }

        public function hwatch(string $hash, string $key, callable $exists = null, callable $notExists = null)
        {
            if ($this->hexists($hash, $key)) {
                if (is_callable($exists)) {
                    return $exists($this->hget($hash, $key));
                }
            } else {
                if (is_callable($notExists)) {
                    return $notExists();
                }
            }

            return false;
        }

        public function hReadAndDelete(string $hash, string $key, $default = null)
        {
            if ($this->hexists($hash, $key)) {
                $value = $this->hget($hash, $key);

                $this->hdelete($hash, $key);

                return $value;
            }

            return $default;
        }

        public function hdelete(string $hash, string $key)
        {
            $key = "hash.$hash.$key";

            return $this->delete($key);
        }

        public function hdel(string $hash, string $key)
        {
            return $this->hdelete($hash, $key);
        }

        public function hhas(string $hash, string $key)
        {
            $key = "hash.$hash.$key";

            return $this->has($key);
        }

        public function hexists(string $hash, string $key)
        {
            return $this->hhas($hash, $key);
        }

        public function hincr(string $hash, string $key, $by = 1)
        {
            $old = $this->hget($hash, $key, 1);
            $new = $old + $by;

            $this->hset($hash, $key, $new);

            return $new;
        }

        public function hdecr(string $hash, string $key, $by = 1)
        {
            $old = $this->hget($hash, $key, 1);
            $new = $old - $by;

            $this->hset($hash, $key, $new);

            return $new;
        }

        /**
         * @param string $hash
         *
         * @return \Generator
         */
        public function hgetall(string $hash)
        {
            $keys = redis()->keys($this->dir . '.hash.' . $hash . '.*');

            foreach ($keys as $row) {
                if (!fnmatch('*.c', $row) && !fnmatch('*.u', $row)) {
                    $key = str_replace("hash.$hash.", '', Arrays::last(explode('.', $row)));

                    yield $key;
                    yield value(unserialize(redis()->get($row)));
                }
            }
        }

        public function hvals($hash)
        {
            $keys = redis()->keys($this->dir . '.hash.' . $hash . '.*');

            foreach ($keys as $row) {
                yield value(unserialize(redis()->get($row)));
            }
        }

        public function hlen($hash)
        {
            $keys = redis()->keys($this->dir . '.hash.' . $hash . '.*');

            return count($keys);
        }

        public function hremove($hash)
        {
            $motor = redis()->pipeline();

            $keys = redis()->keys($this->dir . '.hash.' . $hash . '.*');

            $affected = 0;

            foreach ($keys as $row) {
                $motor->del($row);

                $affected++;
            }

            $motor->execute();

            return $affected;
        }

        public function hkeys($hash)
        {
            $keys = redis()->keys($this->dir . '.hash.' . $hash . '.*');

            foreach ($keys as $row) {
                if (!fnmatch('*.c', $row) && !fnmatch('*.u', $row)) {
                    $key = str_replace("hash.$hash.", '', Arrays::last(explode('.', $row)));

                    yield $key;
                }
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

        public function sinter(...$args)
        {
            $tab = [];

            foreach ($args as $key) {
                $tab = array_intersect($tab, $this->get($key, []));
            }

            return $tab;
        }

        public function sunion(...$args)
        {
            $tab = [];

            foreach ($args as $key) {
                $tab = array_merge($tab, $this->get($key, []));
            }

            return $tab;
        }

        public function sinterstore(...$args)
        {
            $destination = array_shift($args);

            $tab = [];

            foreach ($args as $key) {
                $tab = array_intersect($tab, $this->get($key, []));
            }

            return $this->set($destination, $tab);
        }

        public function sunionstore(...$args)
        {
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

            if ($val !== 'octodummy') {
                $this->set($key, $val);
            } else {
                $val = $this->get($key);
                $this->delete($key);
            }

            return $val !== 'octodummy' ? $this : $val;
        }

        public function add($k, $v, $e)
        {
            if (!$this->has($k)) {
                return $this->set($k, $v, $e);
            }

            return $this;
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

        /**
         * @param string $key
         * @param $value
         *
         * @return Cacheredis
         */
        public function append(string $key, $value)
        {
            $array = $this->get($key, []);

            $array[] = $value;

            return $this->set($key, $array);
        }

        /**
         * @return Collection
         */
        public function toCollection()
        {
            return coll($this->all());
        }

        /**
         * @return array
         */
        public function toArray()
        {
            return $this->all();
        }

        /**
         * @return string
         */
        public function toJson()
        {
            return json_encode($this->all(), JSON_PRETTY_PRINT);
        }

        /**
         * @param array $rows
         * @return Cacheredis
         */
        public function fill(array $rows = []): self
        {
            foreach ($rows as $key => $value) {
                $this->set($key, $value);
            }

            return $this;
        }

        /**
         * @param mixed $offset
         * @return bool
         */
        public function offsetExists($offset)
        {
            return $this->has($offset);
        }

        /**
         * @param mixed $offset
         * @return mixed|null
         */
        public function offsetGet($offset)
        {
            return $this->get($offset);
        }

        /**
         * @param mixed $offset
         * @param mixed $value
         */
        public function offsetSet($offset, $value)
        {
            $this->set($offset, $value);
        }

        /**
         * @param mixed $offset
         */
        public function offsetUnset($offset)
        {
            $this->delete($offset);
        }

        /**
         * @param mixed $offset
         * @return bool
         */
        public function __isset($offset)
        {
            return $this->has($offset);
        }

        /**
         * @param mixed $offset
         * @return mixed|null
         */
        public function __get($offset)
        {
            return $this->get($offset);
        }

        /**
         * @param mixed $offset
         * @param mixed $value
         */
        public function __set($offset, $value)
        {
            $this->set($offset, $value);
        }

        /**
         * @param mixed $offset
         */
        public function __unset($offset)
        {
            $this->delete($offset);
        }
    }
