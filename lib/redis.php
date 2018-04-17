<?php
    namespace Octo;

    use Predis\Client as pc;

    class Redis
    {
        protected $ns, $client, $id;

        public function __construct($ns = null)
        {
            $this->ns = is_null($ns) ? 'core' : $ns;

            $this->id = sha1($this->ns);
        }

        public function __call($m, $a)
        {
            return call_user_func_array([$this->client(), $m], $a);
        }

        public static function __callStatic($m, $a)
        {
            return call_user_func_array([maker(__CLASS__), $m], $a);
        }

        public function setClient($client)
        {
            $this->client = $client;

            return $this;
        }

        protected function client()
        {
            defined("APPLICATION_ENV") || define('APPLICATION_ENV', 'production');

            if (is_null($this->client)) {
                $this->client = new pc([
                    'host'      => Config::get('redis.host', appenv('REDIS_HOST', '127.0.0.1')),
                    'port'      => Config::get('redis.port', appenv('REDIS_PORT', 6379)),
                    'database'  => Config::get('redis.database', appenv('REDIS_DB', 0))
                ]);
            }

            return $this->client;
        }

        public function get($key, $default = null)
        {
            $key = $this->ns . '.' . $key;
            $val = $this->client()->get($key);

            return $val ? $this->unserialize($val) : $default;
        }

        public function age($key)
        {
            $key = $this->ns . '.' . $key;
            $age = $this->client()->hget('key_ages', $key);

            return intval($age);
        }

        public function set($key, $value, $expire = 0)
        {
            $key = $this->ns . '.' . $key;

            $this->client()->set($key, $this->serialize($value));
            $this->client()->hset('key_ages', $key, time());

            if (0 < $expire) {
                $this->client()->expire($key, intval($expire * 60));
            }

            return $this;
        }

        public function replace($key, $value, $expire = 0)
        {
            if ($this->has($key)) {
                $this->set($key, $value, $expire);

                return true;
            }

            return false;
        }

        public function at($k, $v, $timestamp)
        {
            return $this->set($k, $v, ($timestamp - time()) / 60);
        }

        public function forever($k, $v)
        {
            return $this->set($k, $v);
        }

        public function remember($k, $c, $e = 0)
        {
            if (!is_callable($c)) {
                $c = function () use ($c) {return $c;};
            }

            return $this->getOr($k, $c, $e);
        }

        public function setnx($key, $value, $expire = 0)
        {
            if (!$this->has($key)) {
                $this->set($key, $value, $expire);

                return true;
            }

            return false;
        }

        public function destroy($k)
        {
            return $this->delete($k);
        }

        public function setMany(array $values, $expire = 0)
        {
            $this->client()->multi();

            foreach ($values as $key => $value) {
                $this->set($key, $value, $expire);
            }

            $this->client()->exec();
        }

        public function many(array $keys)
        {
            $results = [];

            $values = $this->client()->mget(array_map(function ($key) {
                return $this->ns . '.' . $key;
            }, $keys));

            foreach ($values as $index => $value) {
                $results[$keys[$index]] = !is_null($value) ? $this->unserialize($value) : null;
            }

            return $results;
        }

        public function hset($key, $id, $data)
        {
            $key = $this->ns . '.' . $key;

            $this->client()->hset($key, $id, $this->serialize($data));

            return $this;
        }

        public function hget($key, $id, $default = null)
        {
            $key = $this->ns . '.' . $key;
            $val = $this->client()->hget($key, $id);

            return $val ? $this->unserialize($val) : $default;
        }

        public function hgetall($key)
        {
            $key    = $this->ns . '.' . $key;

            $data   = array_map(function ($row) {
                return $this->unserialize($row);
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

        public function has($key)
        {
            return $this->exists($key);
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

        public function increment($key)
        {
            $key = $this->ns . '.' . $key;

            return $this->client()->incr($key);
        }

        public function decrement($key)
        {
            $key = $this->ns . '.' . $key;

            return $this->client()->decr($key);
        }

        public function flush()
        {
            $this->client()->flushdb();

            return true;
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

        public function session($k, $v = 'dummyget', $e = 0)
        {
            $user       = session('front')->getUser();
            $isLogged   = !is_null($user);
            $key        = $isLogged ? sha1(lng() . '.' . forever() . '1.' . $k) :  sha1(lng() . '.' . forever() . '0.' . $k);

            return 'dummyget' == $v ? $this->get($key) : $this->set($key, $v, $e);
        }

        public function getOr($k, callable $c, $e = 0)
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

        public function view($k, $v = 'dummyget', $e = 0)
        {
            $user       = session('front')->getUser();
            $isLogged   = !is_null($user);
            $key        = $isLogged ? sha1(lng() . '.1.' . $k) :  sha1(lng() . '.0.' . $k);

            return 'dummyget' == $v ? $this->get($key) : $this->set($key, $v, $e);
        }

        public function all($pattern = '*')
        {
            return $this->keys($pattern);
        }

        public function forget($k)
        {
            return $this->delete($k);
        }

        public function readAndDelete($key, $default = null)
        {
            $value = $this->get($key, $default);

            $this->forget($key);

            return $value;
        }

        public function pull($key, $default = null)
        {
            return $this->readAndDelete($key, $default);
        }

        public function getDel($k, $d = null)
        {
            return $this->readAndDelete($k, $d);
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

        public function end($ttl = 60)
        {
            if ($k = Registry::get('cache.buffer.' . $this->id)) {
                $value = ob_get_clean();

                $this->set($k, $value, $this->getTtl($ttl));

                return $value;
            }

            return false;
        }

        /**
         * @param string $k
         * @param callable $c
         * @param int|null $maxAge
         * @param array $args
         * @return int|mixed|null|string
         */
        public function until(string $k, callable $c, ?int $maxAge = null, array $args = [])
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
                if ($maxAge < time()) {
                    $maxAge = ($maxAge * 60) + microtime(true);
                }

                $this->set($keyAge, $maxAge);
            }

            return $data;
        }

        /**
         * @param int $e
         * @return int
         */
        public function getTtl($e = 0)
        {
            return $e ?: appenv('CACHE_TTL', $e);
        }

        protected function serialize($value)
        {
            return is_numeric($value) ? $value : serialize($value);
        }

        protected function unserialize($value)
        {
            return is_numeric($value) ? $value : unserialize($value);
        }

        /**
         * @return pc
         */
        public function getClient()
        {
            return $this->client();
        }

        /**
         * @param callable|null $callback
         * @return array|\Predis\Pipeline\Pipeline
         */
        public function pipeline(?callable $callback = null)
        {
            $pipeline = $this->getClient()->pipeline();

            return is_null($callback)
                ? $pipeline
                : tap($pipeline, $callback)->exec()
            ;
        }

        /**
         * @param callable|null $callback
         * @return mixed
         */
        public function transaction(?callable $callback = null)
        {
            $transaction = $this->getClient()->multi();

            return is_null($callback)
                ? $transaction
                : tap($transaction, $callback)->exec()
            ;
        }
    }
