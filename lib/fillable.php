<?php
namespace Octo;

use ArrayAccess;

class Fillable implements ArrayAccess
{
    private static $data = [];
    /**
     * @var string
     */
    private $namespace;

    public function __construct(string $namespace = 'core')
    {
        $this->namespace = $namespace;

        if (!isset(static::$data[$namespace])) {
            static::$data[$namespace] = [];
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return static::$data[$this->namespace];
    }
    /**
     * @param string $key
     * @return mixed|null
     */
    public function __get(string $key)
    {
        return aget(static::$data[$this->namespace], $key);
    }

    /**
     * @param string $key
     * @param $value
     */
    public function __set(string $key, $value)
    {
        aset(static::$data[$this->namespace], $key, $value);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function __isset(string $key)
    {
        return ahas(static::$data[$this->namespace], $key);
    }

    /**
     * @param string $key
     */
    public function __unset(string $key)
    {
        adel(static::$data[$this->namespace], $key);
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->{$offset});
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->{$key});
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        return $this->{$offset};
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public function get(string $key, $default = null)
    {
        return isset($this->{$key}) ? $this->{$key} : $default;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->{$offset} = $value;
    }

    /**
     * @param string $key
     * @param $value
     * @return Fillable
     */
    public function set(string $key, $value): self
    {
        $this->{$key} = $value;

        return $this;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->{$offset});
    }

    /**
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        $status = $this->has($key);
        unset($this->{$key});

        return $status;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function forget(string $key): bool
    {
        return $this->delete($key);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function remove(string $key): bool
    {
        return $this->delete($key);
    }
}