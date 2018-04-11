<?php
namespace Octo;

use ArrayAccess;

class Flew implements ArrayAccess
{
    /**
     * @var Live
     */
    protected $__bag;

    /**
     * Flew constructor.
     * @param string $ns
     * @throws Exception
     * @throws \ReflectionException
     */
    public function __construct($ns = 'core', $token = null)
    {
        $token = is_null($token) ? You::init() : $token;
        $key = $ns . '.' . $token;
        $this->__bag = new Live(new Caching($key));
    }

    /**
     * @param string $key
     * @param $value
     */
    public function __set(string $key, $value)
    {
        $this->__bag[$key] = $value;
    }

    /**
     * @param  string  $key
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->__bag->{$key} ?? null;
    }

    /**
     * @param $method
     * @param $parameters
     * @return $this|bool|mixed|null
     */
    public function __call($method, $parameters)
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

        return $this->__bag->{$method}(...$parameters);
    }

    /**
     * @param mixed $key
     *
     * @return bool
     */
    public function offsetExists($key)
    {
        return $this->__bag->has($key);
    }

    /**
     * @param mixed $key
     *
     * @return bool
     */
    public function __isset($key)
    {
        return $this->__bag->has($key);
    }

    /**
     * @param mixed $key
     *
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->__bag->get($key);
    }

    /**
     * @param mixed $key
     * @param mixed $value
     */
    public function offsetSet($key, $value)
    {
        $this->__bag[$key] = $value;
    }

    /**
     * @param mixed $key
     */
    public function offsetUnset($key)
    {
        $this->__bag->remove($key);
    }

    /**
     * @param mixed $key
     */
    public function __unset($key)
    {
        $this->__bag->remove($key);
    }
}
