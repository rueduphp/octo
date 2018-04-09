<?php

namespace Octo;

class Parametermemory implements \IteratorAggregate, \Countable, \ArrayAccess
{
    private $__instance;

    /**
     * @param array $parameters
     * @param null|string $instance
     */
    public function __construct(array $parameters = [], ?string $instance = null)
    {
        $this->__instance = !$instance ? sha1(uuid() . uuid()) : $instance;
        setCore('pm.parameters.' . $this->__instance, $parameters);
    }

    /**
     * Returns the parameters.
     *
     * @return array An array of parameters
     */
    public function all()
    {
        return getCore('pm.parameters.' . $this->__instance, []);
    }

    /**
     * Returns the parameter keys.
     *
     * @return array An array of parameter keys
     */
    public function keys()
    {
        return array_keys($this->all());
    }

    /**
     * @param array $parameters
     *
     * @return Parameter
     */
    public function replace(array $parameters = []): self
    {
        $params = $parameters;
        setCore('pm.parameters.' . $this->__instance, $params);

        return $this;
    }

    /**
     * @param array $parameters
     *
     * @return Parameter
     */
    public function add(array $parameters = []): self
    {
        $params = $this->all();
        $params = array_replace($params, $parameters);
        setCore('pm.parameters.' . $this->__instance, $params);

        return $this;
    }

    /**
     * Returns a parameter by name.
     *
     * @param string $key     The key
     * @param mixed  $default The default value if the parameter key does not exist
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        $params = $this->all();

        return array_key_exists($key, $params) ? $params[$key] : $default;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function __get($key)
    {
        if ('parameters' === $key) {
            $key = 'pm.parameters.' . $this->__instance;

            return getCore($key, []);
        }

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
        $params = $this->all();
        $params[$key] = $value;
        setCore('pm.parameters.' . $this->__instance, $params);

        return $this;
    }

    /**
     * @param $key
     * @param $value
     */
    public function __set($key, $value)
    {
        if ('parameters' === $key) {
            $key = 'pm.parameters.' . $this->__instance;

            setCore($key, $value);
        } else {
            $params = $this->all();
            $params[$key] = $value;
            setCore('pm.parameters.' . $this->__instance, $params);
        }
    }

    /**
     * Returns true if the parameter is defined.
     *
     * @param string $key The key
     *
     * @return bool true if the parameter exists, false otherwise
     */
    public function has($key)
    {
        $params = $this->all();

        return array_key_exists($key, $params);
    }

    /**
     * @param $key
     * @return bool
     */
    public function __isset($key)
    {
        if ('parameters' === $key) {
            $key = 'pm.parameters.' . $this->__instance;

            return hasCore($key);
        }

        return array_key_exists($key, $this->parameters);
    }

    /**
     * @param $key
     *
     * @return bool
     */
    public function remove($key)
    {
        $params = $this->all();

        $status = $this->has($key);

        unset($params[$key]);

        setCore('pm.parameters.' . $this->__instance, $params);

        return $status;
    }

    /**
     * @param $key
     */
    public function __unset($key)
    {
        if ('parameters' === $key) {
            $key = 'pm.parameters.' . $this->__instance;

            return delCore($key);
        }

        unset($this->parameters[$key]);
    }

    /**
     * Returns the alphabetic characters of the parameter value.
     *
     * @param string $key     The parameter key
     * @param string $default The default value if the parameter key does not exist
     *
     * @return string The filtered value
     */
    public function getAlpha($key, $default = '')
    {
        return preg_replace('/[^[:alpha:]]/', '', $this->get($key, $default));
    }

    /**
     * Returns the alphabetic characters and digits of the parameter value.
     *
     * @param string $key     The parameter key
     * @param string $default The default value if the parameter key does not exist
     *
     * @return string The filtered value
     */
    public function getAlnum($key, $default = '')
    {
        return preg_replace('/[^[:alnum:]]/', '', $this->get($key, $default));
    }

    /**
     * Returns the digits of the parameter value.
     *
     * @param string $key     The parameter key
     * @param string $default The default value if the parameter key does not exist
     *
     * @return string The filtered value
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
     * Returns the parameter value converted to integer.
     *
     * @param string $key     The parameter key
     * @param int    $default The default value if the parameter key does not exist
     *
     * @return int The filtered value
     */
    public function getInt($key, $default = 0)
    {
        return (int) $this->get($key, $default);
    }

    /**
     * Returns the parameter value converted to boolean.
     *
     * @param string $key     The parameter key
     * @param mixed  $default The default value if the parameter key does not exist
     *
     * @return bool The filtered value
     */
    public function getBoolean($key, $default = false)
    {
        return $this->filter($key, $default, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Filter key.
     *
     * @param string $key     Key
     * @param mixed  $default Default = null
     * @param int    $filter  FILTER_* constant
     * @param mixed  $options Filter options
     *
     * @see http://php.net/manual/en/function.filter-var.php
     *
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
     * Returns an iterator for parameters.
     *
     * @return \ArrayIterator An \ArrayIterator instance
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->all());
    }

    /**
     * Returns the number of parameters.
     *
     * @return int The number of parameters
     */
    public function count()
    {
        return count($this->all());
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
