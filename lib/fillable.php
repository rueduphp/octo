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
        static::$data[$this->namespace][$key] = $value;
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
     * @param mixed $offset
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        return $this->{$offset};
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
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->{$offset});
    }
}