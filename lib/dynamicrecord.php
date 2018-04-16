<?php
namespace Octo;

class Dynamicrecord
{
    /**
     * @var Dynamicmodel
     */
    protected $__db;

    /** @var array  */
    protected $__data;

    public function __construct(array $data = [], Dynamicmodel $db)
    {
        $this->__db = $db;
        $this->__data = $data;
    }

    /**
     * @param $key
     * @param null $default
     * @return mixed|null
     */
    public function get($key, $default = null)
    {
        return array_key_exists($key, $this->__data) ? $this->__data[$key] : $default;
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
        $this->__data[$key] = $value;

        return $this;
    }

    /**
     * @param $key
     * @param $value
     */
    public function __set($key, $value)
    {
        $this->__data[$key] = $value;
    }

    /**
     * @param $key
     * @return bool
     */
    public function has($key)
    {
        return array_key_exists($key, $this->__data);
    }

    /**
     * @param $key
     * @return bool
     */
    public function __isset($key)
    {
        return array_key_exists($key, $this->__data);
    }

    /**
     * @param $key
     *
     * @return bool
     */
    public function remove($key)
    {
        $status = $this->has($key);

        unset($this->__data[$key]);

        return $status;
    }

    /**
     * @param $key
     */
    public function __unset($key)
    {
        unset($this->__data[$key]);
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

    /**
     * @return bool
     */
    public function exists(): bool
    {
        return isset($this->id);
    }

    public function __call(string $method, array $parameters)
    {
        if (fnmatch('get*', $method) && strlen($method) > 3) {
            $key = Inflector::uncamelize(substr($method, 3));
            $default = empty($parameters) ? null : current($parameters);

            return isset($this->{$key}) ? $this->{$key} : $default;
        } elseif (fnmatch('set*', $method) && strlen($method) > 3) {
            $key = Inflector::uncamelize(substr($method, 3));

            $this->{$key} = current($parameters);

            return $this;
        } elseif (fnmatch('has*', $method) && strlen($method) > 3) {
            $key = Inflector::uncamelize(substr($method, 3));

            return isset($this->{$key});
        } elseif (fnmatch('remove*', $method) && strlen($method) > 6) {
            $key = Inflector::uncamelize(substr($method, 6));

            if (isset($this->{$key})) {
                unset($this->{$key});

                return true;
            }

            return false;
        }

        if (isset($this->{$method}) && empty($parameters)) {
            return $this->{$method};
        }

        if (!empty($parameters)) {
            $this->{$method} = current($parameters);

            return $this;
        }
    }
}
