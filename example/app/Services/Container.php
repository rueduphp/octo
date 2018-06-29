<?php
namespace App\Services;

use ArrayAccess;
use IteratorAggregate;
use function Octo\config_path;
use Octo\FastContainerException;
use function Octo\gi;
use function Octo\is_invokable;
use function Octo\isAke;
use Psr\Container\ContainerInterface;
use Traversable;

class Container implements ContainerInterface, ArrayAccess, IteratorAggregate
{
    private static $bound = [];
    private static $factories = [];
    private static $datas = [];

    /**
     * @return array
     */
    public static function getBound(): array
    {
        return self::$bound;
    }

    /**
     * @return array
     */
    public static function getDatas(): array
    {
        return self::$datas;
    }

    /**
     * @return array
     */
    public static function getFactories(): array
    {
        return self::$factories;
    }

    /**
     * @param string $key
     * @param $value
     * @return Container
     */
    public function define(string $key, $value): self
    {
        static::$bound['data.' . $key] = true;
        static::$datas[$key] = $value;

        return $this;
    }

    /**
     * @param string $key
     * @param $value
     * @return Container
     */
    public function push(string $key, $value)
    {
        $array = static::$datas[$key] ?? [];

        $array[] = $value;

        return $this->define($key, $array);
    }

    /**
     * @param string $key
     * @param $value
     * @return Container
     * @throws \ReflectionException
     */
    public function set(string $key, $value): self
    {
        resolver($key, $value, false);

        static::$bound[$key] = true;

        return $this;
    }

    /**
     * @param string $key
     * @param callable $value
     * @return Container
     * @throws \ReflectionException
     */
    public function alter(string $key, callable $value)
    {
        $callback = function () use ($key, $value) {
            return call_func($value, $this->get($key), $this);
        };

        return $this->set($key, $callback);
    }

    /**
     * @param $concern
     * @return Container
     * @throws \ReflectionException
     */
    public function instance($concern)
    {
        return $this->set(get_class($concern), \Octo\toClosure($concern));
    }

    /**
     * @param string $key
     * @param $value
     * @return Container
     * @throws \ReflectionException
     */
    public function factory(string $key, $value): self
    {
        static::$bound[$key] = true;
        static::$factories[$key] = $value;

        return $this;
    }

    /**
     * @param string $id
     * @return mixed|null|object
     * @throws FastContainerException
     * @throws \ReflectionException
     */
    public function get($id)
    {
        $args   = func_get_args();
        $id     = array_shift($args);

        try {
            if (isset(static::$bound['data.' . $id])) {
                $data = isAke(static::$datas, $id, 'octodummy');

                if ('octodummy' !== $data) {
                    return $data;
                }
            }

            if ($factory = isAke(static::$factories, $id, null)) {
                if ($factory instanceof \Closure) {
                    $params = array_merge([$factory], $args);

                    return gi()->makeClosure(...$params);
                } elseif (is_array($factory) && is_callable($factory)) {
                    $params = array_merge($factory, $args);

                    return gi()->call(...$params);
                } elseif (is_object($factory) && is_invokable($factory)) {
                    $params = array_merge([$factory, '__invoke'], $args);

                    return gi()->call(...$params);
                } elseif (is_string($factory) && is_invokable($factory)) {
                    $params = array_merge([$this->get($factory), '__invoke'], $args);

                    return gi()->call(...$params);
                } elseif (is_string($factory) && fnmatch('*::*', $factory)) {
                    list($class, $action) = explode('::', $factory, 2);
                    $params = array_merge([$this->get($class), $action], $args);

                    return gi()->call(...$params);
                } elseif (is_string($factory) && fnmatch('*@*', $factory)) {
                    list($class, $action) = explode('@', $factory, 2);
                    $params = array_merge([$this->get($class), $action], $args);

                    return gi()->call(...$params);
                } else {
                    return $factory;
                }
            }

            $instance =  gi()->make($id, $args);

            if (is_object($instance)) {
                $this->instance($instance);
            }

            return $instance;
        } catch (\Exception $e) {
            throw new FastContainerException($e->getMessage());
        }
    }

    /**
     * @param $id
     * @return mixed|null|object
     * @throws FastContainerException
     * @throws \ReflectionException
     */
    public function resolve($id)
    {
        $args   = func_get_args();
        $id     = array_shift($args);

        if ($id instanceof \Closure) {
            $params = array_merge([$id], $args);

            return gi()->makeClosure(...$params);
        } elseif (is_array($id) && is_callable($id)) {
            $params = array_merge($id, $args);

            return gi()->call(...$params);
        } elseif (is_object($id) && is_invokable($id)) {
            $params = array_merge([$id, '__invoke'], $args);

            return gi()->call(...$params);
        } elseif (is_string($id) && is_invokable($id)) {
            $params = array_merge([$this->get($id), '__invoke'], $args);

            return gi()->call(...$params);
        } elseif (is_string($id) && fnmatch('*::*', $id)) {
            list($class, $action) = explode('::', $id, 2);
            $params = array_merge([$this->get($class), $action], $args);

            return gi()->call(...$params);
        } elseif (is_string($id) && fnmatch('*@*', $id)) {
            list($class, $action) = explode('@', $id, 2);
            $params = array_merge([$this->get($class), $action], $args);

            return gi()->call(...$params);
        } else {
            return gi()->make($id, $args);
        }
    }

    /**
     * @param string $id
     * @return bool
     */
    public function has($id)
    {
        return false !== isAke(static::$bound, $id, false);
    }

    public static function init()
    {
        $container = new static;

        include config_path('container.php');
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset(static::$bound['data.' . $offset]) ||
            isset(static::$bound[$offset]) ||
            isset(static::$factories[$offset])
        ;
    }

    /**
     * @param mixed $offset
     * @return mixed|null|object
     * @throws FastContainerException
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
     */
    public function offsetUnset($offset)
    {
        unset(static::$datas[$offset]);
        unset(static::$factories[$offset]);
        unset(static::$bound[$offset]);
        unset(static::$bound['data.' . $offset]);
    }

    /**
     * @return array|Traversable
     */
    public function getIterator()
    {
        return static::$datas;
    }

    /**
     * @param string $key
     * @param null|mixed $default
     * @return null|mixed
     */
    public function defined(string $key, $default = null)
    {
        return \Octo\isAke(static::$datas, $key, $default);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function isDefined(string $key): bool
    {
        return isset(static::$bound['data.' . $key]);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function forget(string $key): bool
    {
        $status = $this->isDefined($key);

        if (true === $status) {
            unset(static::$datas[$key]);
            unset(static::$bound['data.' . $key]);
        }

        return $status;
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     */
    public function incr(string $key, int $by = 1): int
    {
        $new = (int) $this->defined($key, 0) + $by;
        $this->define($key, $new);

        return $new;
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     */
    public function decr(string $key, int $by = 1): int
    {
        return $this->incr($key, $by * -1);;
    }
}
