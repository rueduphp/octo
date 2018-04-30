<?php
    namespace Octo;

    class Shm
    {
        protected $db, $db_key, $driver, $ns, $size = 10000000;

        /**
         * @param string $ns
         * @throws Exception
         * @throws \Exception
         * @throws \ReflectionException
         */
        public function __construct(string $ns = 'core')
        {
            if (function_exists("shmop_open") === false){
                throw new Exception("\nYour PHP configuration needs adjustment. See: http://us2.php.net/manual/en/shmop.setup.php. To enable the System V shared memory support compile PHP with the option --enable-sysvshm.");
            }

            $this->driver = fmr('shm.' . $ns);
            $this->connect();
        }

        /**
         * @return $this
         * @throws Exception
         * @throws \Exception
         * @throws \ReflectionException
         */
        protected function connect()
        {
            $size = $this->driver->getOr('size', function () {
                return $this->size;
            });

            for ($key = []; count($key) < strlen($this->ns); $key[] = ord(substr($this->ns, sizeof($key), 1)));

            $dbKey = dechex(array_sum($key));
            $dbKey = (int) $dbKey;
            $dbKey += 6;

            $id             = shmop_open($dbKey, "c", 0755, (int) $size);
            $this->db_key   = $dbKey;
            $this->db       = $id;

            return $this;
        }

        /**
         * @return int
         */
        public function free(): int
        {
            return $this->size - shmop_size($this->db);
        }

        /**
         * @throws Exception
         * @throws \Exception
         * @throws \ReflectionException
         */
        public function __destruct()
        {
            /* persistence */
            if ($this->db) {
                $age = $this->driver->getOr('age', function () {
                    return strtotime('yesterday');
                });

                $diff = time() - $age;

                if ($diff > Config::get('shm.persistence', 900)) {
                    $this->write();
                    $this->driver->set('age', time());
                }

                $this->driver->set('size', shmop_size($this->db));

                shmop_close($this->db);
            }
        }

        /**
         * @throws Exception
         * @throws \Exception
         */
        public function recover()
        {
            $data = $this->driver->set('keys', []);

            $this->write($data);
        }

        /**
         * @param null $data
         * @return int
         * @throws Exception
         * @throws \Exception
         */
        public function write($data = null)
        {
            if (is_null($data)) {
                $data = $this->data();

                $this->driver->set('keys', $data);
            }

            shmop_delete($this->db);
            shmop_close($this->db);

            $size       = $this->size > empty($data) ? $this->size : strlen(serialize($data));
            $this->size = $size;
            $this->db   = shmop_open($this->db_key, 'c', 0755, $size);

            $resource   = shmop_write($this->db, serialize($data), 0);

            $this->driver->set('size', shmop_size($this->db));

            return $resource;
        }

        public function data()
        {
            $data   = shmop_read($this->db, 0, 0);
            $data   = !strlen($data) ? [] : unserialize($data);

            return $data;
        }

        /**
         * @param string $key
         * @param $value
         * @param int $expire
         * @return $this
         * @throws Exception
         * @throws \Exception
         */
        public function set(string $key, $value, int $expire = 0)
        {
            $data = $this->data();

            $data[$key]         = value($value);
            $data[$key . '.e']  = $expire;

            $this->write($data);

            return $this;
        }

        /**
         * @param string $key
         * @param null $default
         * @return mixed|null
         */
        public function get(string $key, $default = null)
        {
            list($status, $data) = $this->check($key);

            if (true === $status) {
                return isAke($data, $key, $default);
            }

            return $default;
        }

        /**
         * @param string $key
         * @return bool
         */
        public function has(string $key)
        {
            list($status, $data) = $this->check($key);

            if (true === $status) {
                return 'octodummy' !== isAke($data, $key, 'octodummy');
            }

            return false;
        }

        /**
         * @param $key
         * @return bool|int
         */
        public function expiration($key)
        {
            list($status, $data) = $this->check($key);

            if (true === $status) {
                return (int) isAke($data, $key . '.e', strtotime('yesterday'));
            }

            return false;
        }

        /**
         * @param string $key
         * @return array
         * @throws Exception
         * @throws \Exception
         */
        public function check(string $key)
        {
            $data = $this->data();

            if (isset($data[$key]) && isset($data[$key . '.e'])) {
                $e = (int) isAke($data, $key . '.e', strtotime('yesterday'));

                if ($e < 1) {
                    return [true, $data];
                } else {
                    if ($e < time()) {
                        unset($data[$key]);
                        unset($data[$key . '.e']);

                        $this->write($data);
                    } else {
                        return [true, $data];
                    }
                }
            }

            return [false, $data];
        }

        /**
         * @param string $key
         * @return bool
         * @throws Exception
         * @throws \Exception
         */
        public function delete(string $key)
        {
            $data = $this->data();

            if (isset($data[$key])) {
                unset($data[$key]);
                unset($data[$key . '.e']);

                $this->write($data);

                return true;
            }

            return false;
        }

        /**
         * @param string $key
         * @param $v
         * @param int $expire
         * @return Shm
         * @throws Exception
         * @throws \Exception
         */
        public function add(string $key, $v, int $expire = 0)
        {
            return $this->set($key, $v, $expire);
        }

        /**
         * @param string $key
         * @param $v
         * @param int $expire
         * @return Shm
         * @throws Exception
         * @throws \Exception
         */
        public function setExp(string $key, $v, int $expire = 0)
        {
            return $this->set($key, $v, $expire);
        }

        /**
         * @param string $key
         * @param $v
         * @param int $expire
         * @return Shm
         * @throws Exception
         * @throws \Exception
         */
        public function setExpire(string $key, $v, int $expire = 0)
        {
            return $this->set($key, $v, $expire);
        }

        /**
         * @param string $key
         * @param int $expire
         * @return Shm
         * @throws Exception
         * @throws \Exception
         */
        public function expire(string $key, int $expire = 0)
        {
            $v = $this->get($key);

            return $this->set($key, $v, $expire);
        }

        /**
         * @param string $key
         * @param int $timestamp
         * @return Shm
         * @throws Exception
         * @throws \Exception
         */
        public function expireAt(string $key, int $timestamp)
        {
            $v = $this->get($key);

            return $this->set($key, $v, $timestamp);
        }

        /**
         * @param string $key
         * @param callable $c
         * @param int $e
         * @return mixed|null
         * @throws Exception
         * @throws \Exception
         * @throws \ReflectionException
         */
        public function getOr(string $key, callable $c, int $e = 0)
        {
            if ($this->has($key)) {
                return $this->get($key);
            }

            $res = gi()->makeClosure($c);

            $this->set($key, $res, $e);

            return $res;
        }

        /**
         * @param string $k
         * @param $c
         * @param int $e
         * @return mixed|null
         * @throws Exception
         * @throws \Exception
         * @throws \ReflectionException
         */
        public function remember(string $k, $c, int $e = 0)
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
         * @return bool
         */
        public function watch(string $k, callable $exists = null, callable $notExists = null)
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

        /**
         * @param string $k
         * @param string $v
         * @param int $e
         * @return mixed|null|Shm
         * @throws Exception
         * @throws \Exception
         */
        public function session(string $k, $v = 'dummyget', int $e = 0)
        {
            $user       = session('web')->getUser();
            $isLogged   = !is_null($user);
            $key        = $isLogged ? sha1(lng() . '.' . forever() . '1.' . $k) :  sha1(lng() . '.' . forever() . '0.' . $k);

            return 'dummyget' == $v ? $this->get($key) : $this->set($key, $v, $e);
        }

        /**
         * @param string $k
         * @param callable $c
         * @param $a
         * @return mixed|null
         */
        public function aged(string $k, callable $c, $a)
        {
            $k = sha1($this->ns) . '.' . $k;

            return $this->until($k, $c, $a);
        }

        /**
         * @param string $k
         * @return bool
         * @throws Exception
         * @throws \Exception
         */
        public function forget(string $k)
        {
            return $this->delete($k);
        }

        /**
         * @param string $k
         * @return bool
         * @throws Exception
         * @throws \Exception
         */
        public function del(string $k)
        {
            return $this->delete($k);
        }

        /**
         * @param string $k
         * @return bool
         * @throws Exception
         * @throws \Exception
         */
        public function remove(string $k)
        {
            return $this->delete($k);
        }

        /**
         * @param string $k
         * @param int $by
         * @return int|mixed|null
         * @throws Exception
         * @throws \Exception
         */
        public function incr(string $k, $by = 1)
        {
            $old = $this->get($k, 0);
            $new = $old + $by;

            $this->set($k, $new);

            return $new;
        }

        /**
         * @param string $k
         * @param int $by
         * @return int|mixed|null
         * @throws Exception
         * @throws \Exception
         */
        public function increment(string $k, $by = 1)
        {
            return $this->incr($k, $by);
        }

        /**
         * @param string $k
         * @param int $by
         * @return int|mixed|null
         * @throws Exception
         * @throws \Exception
         */
        public function decr(string $k, $by = 1)
        {
            $old = $this->get($k, 0);
            $new = $old - $by;

            $this->set($k, $new);

            return $new;
        }

        /**
         * @param string $k
         * @param int $by
         * @return int|mixed|null
         * @throws Exception
         * @throws \Exception
         */
        public function decrement(string $k, $by = 1)
        {
            return $this->decr($k, $by);
        }

        /**
         * @param string $pattern
         * @return \Generator
         */
        public function keys(string $pattern = '*')
        {
            $keys = Arrays::pattern($this->data(), $pattern);

            foreach ($keys as $key => $value) {
                if (!fnmatch('*.e', $key)) {
                    yield $key;
                }
            }
        }

        /**
         * @return int
         * @throws Exception
         * @throws \Exception
         */
        public function clean()
        {
            $data = $this->data();

            $keys = Arrays::pattern($data, '*.e');

            $affected = 0;

            foreach ($keys as $key => $age) {
                $age = (int) $age;

                if ($age > 0 && $age < time()) {
                    unset($data[$key]);
                    unset($data[substr($key, 0, -2)]);

                    $affected++;
                }
            }

            if (0 < $affected) {
                $this->write($data);
            }

            return $affected;
        }

        /**
         * @param string $key
         * @param null $default
         * @return mixed|null
         * @throws Exception
         * @throws \Exception
         */
        public function readAndDelete(string $key, $default = null)
        {
            if ($this->has($key)) {
                $value = $this->get($key);

                $this->delete($key);

                return $value;
            }

            return $default;
        }

        /**
         * @param string $keyFrom
         * @param string $keyTo
         * @param null $default
         * @return Shm
         * @throws Exception
         * @throws \Exception
         */
        public function rename(string $keyFrom, string $keyTo, $default = null)
        {
            $value = $this->readAndDelete($keyFrom, $default);

            return $this->set($keyTo, $value);
        }

        /**
         * @param string $keyFrom
         * @param string $keyTo
         * @return Shm
         * @throws Exception
         * @throws \Exception
         */
        public function copy(string $keyFrom, string $keyTo)
        {
            return $this->set($keyTo, $this->get($keyFrom));
        }

        /**
         * @param string $key
         * @return int
         */
        public function getSize(string $key)
        {
            return strlen($this->get($key));
        }

        /**
         * @param string $key
         * @return int
         */
        public function length(string $key)
        {
            return strlen($this->get($key));
        }

        /**
         * @param string $key
         * @param $value
         * @return Shm
         * @throws Exception
         * @throws \Exception
         */
        public function sadd(string $key, $value)
        {
            $tab = $this->get($key, []);
            $tab[] = $value;

            return $this->set($key, $tab);
        }

        /**
         * @param string $key
         * @return int
         */
        public function scard(string $key)
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

        /**
         * @param array ...$args
         * @return Shm
         * @throws Exception
         * @throws \Exception
         */
        public function sinterstore(...$args)
        {
            $destination = array_shift($args);

            $tab = [];

            foreach ($args as $key) {
                $tab = array_intersect($tab, $this->get($key, []));
            }

            return $this->set($destination, $tab);
        }

        /**
         * @param array ...$args
         * @return Shm
         * @throws Exception
         * @throws \Exception
         */
        public function sunionstore(...$args)
        {
            $destination = array_shift($args);

            $tab = [];

            foreach ($args as $key) {
                $tab = array_merge($tab, $this->get($key, []));
            }

            return $this->set($destination, $tab);
        }

        /**
         * @param string $hash
         * @param string $key
         * @return bool
         */
        public function sismember(string $hash, string $key)
        {
            return in_array($key, $this->get($hash, []));
        }

        /**
         * @param string $key
         * @return mixed|null
         */
        public function smembers(string $key)
        {
            return $this->get($key, []);
        }

        /**
         * @param string $hash
         * @param string $key
         * @return bool
         * @throws Exception
         * @throws \Exception
         */
        public function srem(string $hash, string $key)
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
         * @param string $from
         * @param string $to
         * @param string $key
         * @return bool
         * @throws Exception
         * @throws \Exception
         */
        public function smove(string $from, string $to, string $key)
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
         * @param string $k
         * @param callable $c
         * @param null $maxAge
         * @param array $args
         * @return mixed|null
         * @throws Exception
         * @throws \Exception
         */
        public function until(string $k, callable $c, $maxAge = null, $args = [])
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

        /**
         * @param string $val
         * @return null|string|string[]
         */
        protected function key(string $val)
        {
            $val = $this->ns . '.' . $val;

            return preg_replace(
                "/[^0-9]/",
                "",
                (preg_replace(
                    "/[^0-9]/",
                    "",
                    md5($val)
                ) / 35676248) / 619876
            );
        }
    }
