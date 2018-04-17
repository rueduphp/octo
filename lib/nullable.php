<?php

namespace Octo;

use ArrayAccess;

class Nullable implements ArrayAccess
{
    use Macroable {
        __call as macroCall;
    }

    /**
     * @var mixed
     */
    protected $value;

    /**
     * @param  mixed  $value
     * @return void
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        if (is_object($this->value)) {
            return $this->value->{$key} ?? null;
        }

        return null;
    }

    /**
     * @param $method
     * @param $parameters
     *
     * @return mixed|null
     *
     * @throws \ReflectionException
     */
    public function __call($method, $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        if (is_object($this->value)) {
            return $this->value->{$method}(...$parameters);
        }

        return null;
    }

    /**
     * @param mixed $key
     *
     * @return bool
     */
    public function offsetExists($key)
    {
        return Arrays::accessible($this->value) && Arrays::exists($this->value, $key);
    }

    /**
     * @param mixed $key
     *
     * @return mixed
     */
    public function offsetGet($key)
    {
        return Arrays::get($this->value, $key);
    }

    /**
     * @param mixed $key
     * @param mixed $value
     */
    public function offsetSet($key, $value)
    {
        if (Arrays::accessible($this->value)) {
            $this->value[$key] = $value;
        }
    }

    /**
     * @param mixed $key
     */
    public function offsetUnset($key)
    {
        if (Arrays::accessible($this->value)) {
            unset($this->value[$key]);
        }
    }
}
