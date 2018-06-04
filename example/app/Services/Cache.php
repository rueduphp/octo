<?php

namespace App\Services;

use Closure;

class Cache
{
    /**
     * @var \Illuminate\Contracts\Redis\Factory
     */
    protected $redis;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var string
     */
    protected $connection;

    /**
     * @var int
     */
    private $ttl;

    /**
     * @param int $ttl
     * @param string $prefix
     * @param string $connection
     */
    public function __construct($ttl = 60, $prefix = '', $connection = 'default')
    {
        $this->redis = dic('redis');
        $this->setPrefix($prefix);
        $this->setConnection($connection);
        $this->ttl = $ttl;
    }

    /**
     * @param  string|array  $key
     * @return mixed
     */
    public function get($key, $default = null)
    {
        $value = $this->connection()->get($this->prefix.$key);

        return !is_null($value) ? $this->unserialize($value) : $default;
    }

    /**
     * @param  array  $keys
     * @return array
     */
    public function many(array $keys)
    {
        $results = [];

        $values = $this->connection()->mget(array_map(function ($key) {
            return $this->prefix.$key;
        }, $keys));

        foreach ($values as $index => $value) {
            $results[$keys[$index]] = !is_null($value) ? $this->unserialize($value) : null;
        }

        return $results;
    }

    /**
     * @param  string  $key
     * @param  mixed   $value
     * @param  float|int  $minutes
     * @return Cache
     */
    public function put(string $key, $value, $minutes = 0): self
    {
        $minutes = !is_numeric($minutes) ? $this->ttl : $minutes;
        $minutes = 0 === $minutes ? $this->ttl : $minutes;

        $this->connection()->setex(
            $this->prefix.$key, (int) max(1, $minutes * 60), $this->serialize($value)
        );

        return $this;
    }

    /**
     * @param string $key
     * @param $value
     * @param int $minutes
     * @return Cache
     */
    public function set(string $key, $value, $minutes = 0): self
    {
        return $this->put($key, $value, $minutes);
    }

    /**
     * @param  array  $values
     * @param  float|int  $minutes
     * @return void
     */
    public function putMany(array $values, $minutes = 0)
    {
        $minutes = !is_numeric($minutes) ? $this->ttl : $minutes;
        $minutes = 0 === $minutes ? $this->ttl : $minutes;

        $this->connection()->multi();

        foreach ($values as $key => $value) {
            $this->put($key, $value, $minutes);
        }

        $this->connection()->exec();
    }

    /**
     * @param $key
     * @param $value
     * @param int $minutes
     * @return bool
     * @throws \ReflectionException
     */
    public function add($key, $value, $minutes = 0)
    {
        $minutes = !is_numeric($minutes) ? $this->ttl : $minutes;
        $minutes = 0 === $minutes ? $this->ttl : $minutes;

        $lua = "return redis.call('exists',KEYS[1])<1 and redis.call('setex',KEYS[1],ARGV[2],ARGV[1])";

        return (bool) $this->connection()->eval(
            $lua, 1, $this->prefix.$key, $this->serialize(\Octo\value($value)), (int) max(1, $minutes * 60)
        );
    }

    /**
     * @param  string  $key
     * @param  mixed   $value
     * @return int
     */
    public function increment($key, $value = 1)
    {
        return $this->connection()->incrby($this->prefix.$key, $value);
    }

    /**
     * @param  string  $key
     * @param  mixed   $value
     * @return int
     */
    public function decrement($key, $value = 1)
    {
        return $this->connection()->decrby($this->prefix.$key, $value);
    }

    /**
     * @param  string  $key
     * @param  mixed   $value
     * @return int
     */
    public function incr($key, $value = 1)
    {
        return $this->connection()->incrby($this->prefix.$key, $value);
    }

    /**
     * @param  string  $key
     * @param  mixed   $value
     * @return int
     */
    public function decr($key, $value = 1)
    {
        return $this->connection()->decrby($this->prefix.$key, $value);
    }

    /**
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function forever($key, $value)
    {
        $this->connection()->set($this->prefix.$key, $this->serialize($value));
    }

    /**
     * @param  string  $key
     * @return bool
     */
    public function forget($key)
    {
        return (bool) $this->connection()->del($this->prefix.$key);
    }

    /**
     * @param  string  $key
     * @return bool
     */
    public function delete($key)
    {
        return (bool) $this->connection()->del($this->prefix.$key);
    }

    /**
     * @return bool
     */
    public function flush()
    {
        $this->connection()->flushdb();

        return true;
    }

    /**
     * @return \Predis\ClientInterface
     */
    public function connection()
    {
        return $this->redis->connection($this->connection);
    }

    /**
     * @param $connection
     * @return Cache
     */
    public function setConnection($connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * @return \Illuminate\Contracts\Redis\Factory
     */
    public function getRedis()
    {
        return $this->redis;
    }

    /**
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * @param $prefix
     * @return Cache
     */
    public function setPrefix($prefix): self
    {
        $this->prefix = ! empty($prefix) ? $prefix.':' : '';

        return $this;
    }

    /**
     * @param $value
     * @return int|string
     */
    protected function serialize($value)
    {
        return is_numeric($value) ? $value : serialize($value);
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    protected function unserialize($value)
    {
        return is_numeric($value) ? $value : unserialize($value);
    }

    /**
     * @param int $ttl
     * @return Cache
     */
    public function setTtl(int $ttl): self
    {
        $this->ttl = $ttl;

        return $this;
    }

    /**
     * @param string $key
     * @param Closure $callable
     * @param int $minutes
     * @return mixed
     * @throws \ReflectionException
     */
    public function getOr(string $key, Closure $callable, $minutes = 0)
    {
        $result = $this->get($key, 'octodummy');

        if ('octodummy' === $result) {
            $this->set($key, $result = \Octo\gi()->makeClosure($callable), $minutes);
        }

        return $result;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return 'octodummy' !== $this->get($key, 'octodummy');
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return $this->connection()->{$name}(...$arguments);
    }
}
