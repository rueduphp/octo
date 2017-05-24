<?php
    namespace Octo;

    class Shm
    {
        protected $db, $db_key, $driver, $ns, $size = 10000000;

        public function __construct($ns = 'core')
        {
            if (function_exists("shmop_open") === false){
                throw new Exception("\nYour PHP configuration needs adjustment. See: http://us2.php.net/manual/en/shmop.setup.php. To enable the System V shared memory support compile PHP with the option --enable-sysvshm.");
            }

            $this->driver = fmr('shm.' . $ns);
            $this->connect();
        }

        protected function connect()
        {
            $size = $this->driver->getOr('size', function () {
                return $this->size;
            });

            for($key = []; count($key) < strlen($this->ns); $key[] = ord(substr($this->ns, sizeof($key), 1)));

            $dbKey = dechex(array_sum($key));
            $dbKey = (int) $dbKey;
            $dbKey += 6;

            $id             = shmop_open($dbKey, "c", 0755, (int) $size);
            $this->db_key   = $dbKey;
            $this->db       = $id;

            return $this;
        }

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

        public function recover()
        {
            $data = $this->driver->set('keys', []);

            $this->write($data);
        }

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

        public function set($key, $value, $expire = 0)
        {
            $data = $this->data();

            $data[$key]         = value($value);
            $data[$key . '.e']  = $expire;

            $this->write($data);

            return $this;
        }

        public function get($key, $default = null)
        {
            list($status, $data) = $this->check($key);

            if (true === $status) {
                return isAke($data, $key, $default);
            }

            return $default;
        }

        public function has($key)
        {
            list($status, $data) = $this->check($key);

            if (true === $status) {
                return 'octodummy' != isAke($data, $key, 'octodummy');
            }

            return false;
        }

        public function expiration($key)
        {
            list($status, $data) = $this->check($key);

            if (true === $status) {
                return (int) isAke($data, $key . '.e', strtotime('yesterday'));
            }

            return false;
        }

        public function check($key)
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

        public function delete($key)
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

        public function add($k, $v, $expire = 0)
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
            $k = sha1($this->ns) . '.' . $k;

            return $this->until($k, $c, $a);
        }

        public function forget($k)
        {
            return $this->delete($k);
        }

        public function del($k)
        {
            return $this->delete($k);
        }

        public function remove($k)
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
            $keys = Arrays::pattern($this->data(), $pattern);

            foreach ($keys as $key => $value) {
                if (!fnmatch('*.e', $key)) yield $key;
            }
        }

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

        protected function key($val)
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
