<?php

namespace Octo;

class Parameter implements \IteratorAggregate, \Countable, \ArrayAccess
{
    /**
     * @var array
     */
    protected $parameters;

    /**
     * @param array $parameters An array of parameters
     */
    public function __construct(array $parameters = [])
    {
        $this->parameters = $parameters;
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->parameters;
    }

    /**
     * @return array
     */
    public function keys()
    {
        return array_keys($this->parameters);
    }

    /**
     * @param array $parameters
     *
     * @return Parameter
     */
    public function replace(array $parameters = []): self
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * @param array $parameters
     *
     * @return Parameter
     */
    public function add(array $parameters = []): self
    {
        $this->parameters = array_replace($this->parameters, $parameters);

        return $this;
    }

    /**
     * @param $key
     * @param null $default
     * @return mixed|null
     */
    public function get($key, $default = null)
    {
        return array_key_exists($key, $this->parameters) ? $this->parameters[$key] : $default;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * @param $key
     * @param $value
     *
     * @return $this
     */
    public function set($key, $value)
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    /**
     * @param $key
     * @param $value
     */
    public function __set($key, $value)
    {
        $this->parameters[$key] = $value;
    }

    /**
     * @param $key
     * @return bool
     */
    public function has($key)
    {
        return array_key_exists($key, $this->parameters);
    }

    /**
     * @param $key
     * @return bool
     */
    public function __isset($key)
    {
        return array_key_exists($key, $this->parameters);
    }

    /**
     * @param $key
     *
     * @return bool
     */
    public function remove($key)
    {
        $status = $this->has($key);

        unset($this->parameters[$key]);

        return $status;
    }

    /**
     * @param $key
     */
    public function __unset($key)
    {
        unset($this->parameters[$key]);
    }

    /**
     * @param $key
     * @param string $default
     * @return null|string|string[]
     */
    public function getAlpha($key, $default = '')
    {
        return preg_replace('/[^[:alpha:]]/', '', $this->get($key, $default));
    }

    /**
     * @param $key
     * @param string $default
     * @return null|string|string[]
     */
    public function getAlnum($key, $default = '')
    {
        return preg_replace('/[^[:alnum:]]/', '', $this->get($key, $default));
    }

    /**
     * @param $key
     * @param string $default
     * @return mixed
     */
    public function getDigits($key, $default = '')
    {
        return str_replace(
            array('-', '+'),
            '',
            $this->filter(
                $key,
                $default,
                FILTER_SANITIZE_NUMBER_INT
            )
        );
    }

    /**
     * @param $key
     * @param int $default
     * @return int
     */
    public function getInt($key, $default = 0)
    {
        return (int) $this->get($key, $default);
    }

    /**
     * @param $key
     * @param bool $default
     * @return mixed
     */
    public function getBoolean($key, $default = false)
    {
        return $this->filter($key, $default, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param $key
     * @param null $default
     * @param int $filter
     * @param array $options
     * @return mixed
     */
    public function filter($key, $default = null, $filter = FILTER_DEFAULT, $options = [])
    {
        $value = $this->get($key, $default);

        if (!is_array($options) && $options) {
            $options = array('flags' => $options);
        }

        if (is_array($value) && !isset($options['flags'])) {
            $options['flags'] = FILTER_REQUIRE_ARRAY;
        }

        return filter_var($value, $filter, $options);
    }

    /**
     * @return \ArrayIterator|\Traversable
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->parameters);
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->parameters);
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }
}
