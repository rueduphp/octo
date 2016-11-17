<?php
    namespace Octo;

    use \Predis\Client as pc;

    class Redis
    {
        private $ns;
        private $client;

        public function __construct($ns = null)
        {
            $this->ns = is_null($ns) ? 'core' : $ns;
        }

        private function client()
        {
            defined("APPLICATION_ENV") || define('APPLICATION_ENV', 'production');

            if (is_null($this->client)) {
                $this->client = new pc([
                    'host'      => Config::get('redis.host', '127.0.0.1'),
                    'port'      => Config::get('redis.port', 6379),
                    'database'  => Config::get('redis.database', 0)
                ]);
            }

            return $this->client;
        }

        public function get($key, $default = null)
        {
            $key = $this->ns . '.' . $key;
            $val = $this->client()->get($key);

            return $val ? unserialize($val) : $default;
        }

        public function age($key)
        {
            $key = $this->ns . '.' . $key;
            $age = $this->client()->hget('key_ages', $key);

            return $age;
        }

        public function set($key, $value, $expire = 0)
        {
            $key = $this->ns . '.' . $key;

            $this->client()->set($key, serialize($value));
            $this->client()->hset('key_ages', $key, time());

            if (0 < $expire) {
                $this->client()->expire($key, $expire);
            }

            return $this;
        }

        public function hset($key, $id, $data)
        {
            $key = $this->ns . '.' . $key;

            $this->client()->hset($key, $id, serialize($data));

            return $this;
        }

        public function hget($key, $id, $default = null)
        {
            $key = $this->ns . '.' . $key;
            $val = $this->client()->hget($key, $id);

            return $val ? unserialize($val) : $default;
        }

        public function hgetall($key, $default = null)
        {
            $key    = $this->ns . '.' . $key;

            $data   = array_map(function ($row) {
                return unserialize($row);
            }, array_values($this->client()->hgetall($key)));

            return array_values(array_unique($data));
        }

        public function delete($key)
        {
            $key = $this->ns . '.' . $key;

            $this->client()->del($key);
            $this->client()->hdel('key_ages', $key);

            return $this;
        }

        public function del($key)
        {
            $key = $this->ns . '.' . $key;

            $this->client()->del($key);
            $this->client()->hdel('key_ages', $key);

            return $this;
        }

        public function hdel($key, $id)
        {
            $key = $this->ns . '.' . $key;

            $this->client()->hdel($key, $id);

            return $this;
        }

        public function exists($key)
        {
            $key = $this->ns . '.' . $key;

            return $this->client()->exists($key);
        }

        public function incrby($key, $by = 1)
        {
            $key = $this->ns . '.' . $key;

            return $this->client()->incrby($key, $by);
        }

        public function decrby($key, $by = 1)
        {
            $key = $this->ns . '.' . $key;

            return $this->client()->decrby($key, $by);
        }

        public function incr($key)
        {
            $key = $this->ns . '.' . $key;

            return $this->client()->incr($key);
        }

        public function decr($key)
        {
            $key = $this->ns . '.' . $key;

            return $this->client()->decr($key);
        }

        public function keys($pattern)
        {
            $pattern = $this->ns . '.' . $pattern;

            return $this->client()->keys($pattern);
        }

        public function hkeys($pattern)
        {
            $pattern = $this->ns . '.' . $pattern;

            return $this->client()->hkeys($pattern);
        }

        public function hlen($pattern)
        {
            $pattern = $this->ns . '.' . $pattern;

            return $this->client()->hlen($pattern);
        }

        public function count()
        {
            $pattern = $this->ns . '.*';

            return count($this->client()->keys($pattern));
        }

        public function session($k, $v = 'dummyget', $e = null)
        {
            $user       = session('front')->getUser();
            $isLogged   = !is_null($user);
            $key        = $isLogged ? sha1(lng() . '.' . forever() . '1.' . $k) :  sha1(lng() . '.' . forever() . '0.' . $k);

            return 'dummyget' == $v ? $this->get($key) : $this->set($key, $v, $e);
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

        public function view($k, $v = 'dummyget', $e = null)
        {
            $user       = session('front')->getUser();
            $isLogged   = !is_null($user);
            $key        = $isLogged ? sha1(lng() . '.1.' . $k) :  sha1(lng() . '.0.' . $k);
            // $key = sha1(lng() . $k);

            return 'dummyget' == $v ? $this->get($key) : $this->set($key, $v, $e);
        }
    }
