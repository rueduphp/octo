<?php
namespace Octo;

use ArrayAccess;
use Closure;

class In implements ArrayAccess
{
    /** @var In */
    private static $instance;

    /**
     * @return In
     */
    public static function self(): self
    {
        if (!isset(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * @param array ...$args
     * @return In
     * @throws \ReflectionException
     */
    public static function constructWith(...$args): self
    {
        $class = array_shift($args);
        $instance = gi()->make($class, $args);

        $callback = function () use ($instance) {
            return $instance;
        };

        return static::set($class, $callback);
    }

    /**
     * @param string $name
     * @param Closure|null $callback
     * @return mixed|In
     * @throws \ReflectionException
     */
    public static function lazy(string $name, ?Closure $callback = null)
    {
        if (null === $callback) {
            $callback = static::get($name);

            if ($callback instanceof Closure) {
                return gi()->makeClosure($callback);
            }
        } else {
            static::set($name, callLater($callback));
        }

        return static::self();
    }

    /**
     * @param string $name
     * @param callable $callable
     * @return In
     * @throws \ReflectionException
     */
    public static function callable(string $name, callable $callable)
    {
        if ($callable instanceof Closure) {
            return static::lazy($name, $callable);
        } else if (is_array($callable)) {
            $callback = function () use ($callable) {
                return gi()->call($callable);
            };

            return static::lazy($name, $callback);
        } else if (is_object($callable) && in_array('__invoke', get_class_methods($callable))) {
            $callback = function () use ($callable) {
                $toCall = [$callable, '__invoke'];

                return gi()->call($toCall);
            };

            return static::lazy($name, $callback);
        }

        return static::self();
    }

    /**
     * @param string $key
     * @param $callable
     * @return In
     * @throws \ReflectionException
     */
    public static function singleton(string $key, $callable): self
    {
        $instance = callThat($callable);

        return static::set($key, $instance);
    }

    /**
     * @param array ...$args
     * @return In
     * @throws \ReflectionException
     */
    public static function set(...$args): self
    {
        gi()->set(...$args);

        return static::self();
    }

    /**
     * @param string $key
     * @param $callable
     * @return In
     * @throws \ReflectionException
     */
    public static function setIf(string $key, $callable)
    {
        if (!static::has($key)) {
            static::set($key, $callable);
        }

        return static::self();
    }

    /**
     * @param string $from
     * @param string $alias
     * @return In
     * @throws \ReflectionException
     */
    public static function alias(string $from, string $alias): self
    {
        $self = static::self();

        $instance = $self->get($from);

        $callback = function () use ($instance) {
            return $instance;
        };

        $self->set($alias, $callback);

        return $self;
    }

    /**
     * @param $object
     * @return In
     * @throws \ReflectionException
     */
    public static function setInstance($object): self
    {
        if (is_object($object)) {
            $class = get_class($object);

            $callback = function () use ($object) {
                return $object;
            };

            return static::set($class, $callback);
        }

        return static::self();
    }

    /**
     * @param $name
     * @param $class
     * @return In
     * @throws \ReflectionException
     */
    public function anonymous($name, $class)
    {
        if (is_object($class)) {
            $callback = function () use ($class) {
                return $class;
            };

            return static::set($name, $callback);
        }

        return static::self();
    }

    /**
     * @param array ...$args
     * @return mixed|null|object
     * @throws \ReflectionException
     */
    public static function get(...$args)
    {
        $value = gi()->get(...$args);

        if ($value instanceof Closure) {
            $value = gi()->makeClosure($value);
        } else {
            if (null === $value) {
                $class = array_shift($args);

                if (class_exists($class)) {
                    $instance =  gi()->make($class, $args, true);
                    static::self()[$class] = $instance;

                    return $instance;
                }
            }
        }

        return $value;
    }

    /**
     * @param array ...$args
     * @return bool
     * @throws \ReflectionException
     */
    public static function has(...$args): bool
    {
        return gi()->has(...$args);
    }

    /**
     * @param array ...$args
     * @return bool
     * @throws \ReflectionException
     */
    public static function forget(...$args): bool
    {
        return gi()->del(...$args);
    }

    /**
     * @param string $key
     * @param $value
     * @throws \ReflectionException
     */
    public function __set(string $key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * @param string $key
     * @return mixed|null|object
     * @throws \ReflectionException
     */
    public function __get(string $key)
    {
        return $this->get($key);
    }

    /**
     * @param string $key
     * @return bool
     * @throws \ReflectionException
     */
    public function __isset(string $key)
    {
        return $this->has($key);
    }

    /**
     * @param string $key
     * @throws \ReflectionException
     */
    public function __unset(string $key)
    {
        $this->forget($key);
    }

    /**
     * @param mixed $offset
     * @return bool
     * @throws \ReflectionException
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * @param mixed $offset
     * @return mixed|null|object
     * @throws \ReflectionException
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @throws \ReflectionException
     */
    public function offsetSet($offset, $value)
    {
       $this->set($offset, $value);
    }

    /**
     * @param mixed $offset
     * @throws \ReflectionException
     */
    public function offsetUnset($offset)
    {
        $this->forget($offset);
    }
}