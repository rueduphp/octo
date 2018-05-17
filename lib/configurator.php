<?php

namespace Octo;

use ArrayAccess;

class Configurator implements ArrayAccess
{
    /**
     * @var array
     */
    protected $items = [];

    /**
     * @param  array  $items
     * @return void
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * @param  string  $key
     * @return bool
     */
    public function has($key): bool
    {
        return ahas($this->items, $key);
    }

    /**
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return aget($this->items, $key, $default);
    }

    /**
     * @param array|string  $key
     * @param mixed $value
     * @return Configuration
     */
    public function set($key, $value = null): self
    {
        $keys = is_array($key) ? $key : [$key => $value];

        foreach ($keys as $key => $value) {
            aset($this->items, $key, $value);
        }

        return $this;
    }

    /**
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function prepend($key, $value)
    {
        $array = $this->get($key);

        array_unshift($array, $value);

        $this->set($key, $array);
    }

    /**
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function push($key, $value)
    {
        $array = $this->get($key);

        $array[] = $value;

        $this->set($key, $array);
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->items;
    }

    /**
     * @param  string  $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return $this->has($key);
    }

    /**
     * @param  string  $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->get($key);
    }

    /**
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * @param  string  $key
     * @return void
     */
    public function offsetUnset($key)
    {
        $this->set($key, null);
    }
}
