<?php
namespace Octo;

use ArrayAccess;
use Closure;
use IteratorAggregate;

class Pack implements ArrayAccess, IteratorAggregate
{
    /**
     * @var mixed
     */
    protected $__bag;

    /**
     * @param  array  $data
     * @return void
     */
    public function __construct(array $data = [])
    {
        $this->__bag = new Parametermemory($data);
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
     * @param string $key
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function __get(string $key)
    {
        $value = $this->__bag->{$key} ?? null;

        if ($value instanceof Closure) {
            return gi()->makeClosure($value, $this);
        } elseif (is_callable($value) && is_array($value)) {
            return gi()->call($value, $this);
        } elseif (is_callable($value) && is_object($value)) {
            return gi()->call($value, '__invoke', $this);
        }

        return $value;
    }

    /**
     * @param $method
     * @param $parameters
     * @return Component|mixed
     * @throws \ReflectionException
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

        if (!empty($parameters) && count($parameters) === 1 && is_callable(current($parameters))) {
            $this->__bag->{$method} = current($parameters);

            return $this;
        }

        if (in_array($method, get_class_methods($this->__bag))) {
            return $this->__bag->{$method}(...$parameters);
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
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function offsetGet($key)
    {
        return $this->__get($key);
    }

    /**
     * @param mixed $key
     * @param mixed $value
     */
    public function offsetSet($key, $value)
    {
        $this->__set($key, $value);
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

    public function getIterator()
    {
        return new \ArrayIterator($this->toArray());
    }
}
