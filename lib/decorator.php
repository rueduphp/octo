<?php
namespace Octo;

use ArrayAccess;
use Closure;

class Decorator implements ArrayAccess
{
    /**
     * @var mixed
     */
    protected $__bag;

    /**
     * @param  array  $data
     * @return void
     */
    public function __construct($concern)
    {
        $this->__bag = new Parametermemory;

        $this['__reveal'] = toClosure($concern);
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
     * @param string $method
     * @param array $parameters
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function __call(string $method, array $parameters)
    {
        if ($callable = $this->__bag->{$method}) {
            if (is_callable($callable)) {
                if ($callable instanceof Closure) {
                    $args = array_merge([$callable], $parameters);

                    return gi()->makeClosure(...$args);
                } elseif (is_array($callable)) {
                    $args = array_merge($callable, $parameters);

                    return gi()->call(...$args);
                }
            } else {
                return $callable;
            }
        }

        $params = array_merge([$this->__reveal(), $method], $parameters);

        return gi()->call(...$params);
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
        $this->__bag->set($key, $value);
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
