<?php
namespace Octo;

class Lazycache implements FastCacheInterface, \ArrayAccess
{
    /** @var Cache */
    private $driver;

    /** @var array */
    private $data = [];

    public function __construct($driver)
    {
        $this->driver = $driver;
    }

    /**
     * @param string $key
     * @return bool
     * @throws Exception
     */
    public function has(string $key)
    {
        return isset($this->initKey($key)->data[$key]);
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed|null
     * @throws Exception
     */
    public function get(string $key, $default = null)
    {
        if (true === $this->has($key)) {
            return $this->data[$key];
        }

        return $default;
    }

    /**
     * @param string $key
     * @return Lazycache
     * @throws Exception
     */
    private function initKey(string $key): self
    {
        if (!array_key_exists($key, $this->data)) {
            $this->data[$key] = $this->driver->get($key);
        }

        return $this;
    }

    /**
     * @param string $key
     * @param $value
     * @return Lazycache
     * @throws Exception
     */
    public function set(string $key, $value): self
    {
        $this->driver->set($key, $value);
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * @param string $key
     * @return bool
     * @throws Exception
     */
    public function remove(string $key): bool
    {
        $status = $this->has($key);
        unset($this->data[$key]);
        $this->driver->remove($key);

        return $status;
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed|null
     * @throws Exception
     */
    public function pull(string $key, $default = null)
    {
        $value = $this->get($key, $default);

        $this->remove($key);

        return $value;
    }

    /**
     * @return Lazycache
     * @throws \Exception
     */
    public function flush(): self
    {
        $this->data = [];
        $this->driver->flush();

        return $this;
    }

    public function count(): int
    {
        return count($this->data);
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return 0 === $this->count();
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     * @throws Exception
     */
    public function increment(string $key, int $by = 1): int
    {
        $this->set($key, $value = $this->get($key, 0) + $by);

        return $value;
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     * @throws Exception
     */
    public function decrement(string $key, int $by = 1): int
    {
        return $this->increment($key, $by * -1);
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     * @throws Exception
     */
    public function incr(string $key, int $by = 1): int
    {
        return $this->increment($key, $by);
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     * @throws Exception
     */
    public function decr(string $key, int $by = 1): int
    {
        return $this->increment($key, $by * -1);
    }

    /**
     * @param $key
     * @param null $value
     * @return Lazycache
     * @throws Exception
     */
    public function put($key, $value = null): self
    {
        if (!is_array($key) && null === $value) {
            $key = [$key => $value];
        }

        foreach ($key as $arrayKey => $arrayValue) {
            $this->set($arrayKey, $arrayValue);
        }

        return $this;
    }

    /**
     * @param $data
     * @return Lazycache
     * @throws Exception
     */
    public function many($data): self
    {
        $data = arrayable($data) ? $data->toArray() : $data;

        return $this->put($data);
    }

    /**
     * @param array $attributes
     * @return Lazycache
     * @throws Exception
     * @throws \Exception
     */
    public function replace(array $attributes): self
    {
        $this->flush();

        return $this->put($attributes);
    }

    /**
     * @param array $attributes
     * @return Lazycache
     * @throws Exception
     */
    public function merge(array $attributes): self
    {
        return $this->put($attributes);
    }

    /**
     * @param string $key
     * @return bool
     * @throws Exception
     */
    public function __isset(string $key)
    {
        return $this->has($key);
    }

    /**
     * @param string $key
     * @throws Exception
     */
    public function __unset(string $key)
    {
        $this->remove($key);
    }

    /**
     * @param string $key
     * @param $value
     * @throws Exception
     */
    public function __set(string $key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * @param string $key
     * @return mixed|null
     * @throws Exception
     */
    public function __get(string $key)
    {
        return $this->get($key);
    }

    /**
     * @param array $rows
     * @return Lazycache
     * @throws Exception
     */
    public function fill(array $rows = []): self
    {
        foreach ($rows as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * @param string $key
     * @param $value
     * @return Lazycache
     * @throws Exception
     */
    public function push(string $key, $value)
    {
        $array = $this->get($key, []);

        $array[] = $value;

        return $this->set($key, $array);
    }

    /**
     * @param string $key
     * @return mixed|null
     * @throws Exception
     */
    function pushDown(string $key)
    {
        return $this->pull($key);
    }

    /**
     * @param $offset
     * @return bool
     * @throws Exception
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * @param $offset
     * @return mixed|null
     * @throws Exception
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @param $offset
     * @param $value
     * @throws Exception
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * @param $offset
     * @throws Exception
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }
}
