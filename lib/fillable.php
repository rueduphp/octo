<?php
namespace Octo;

use ArrayAccess;

class Fillable implements ArrayAccess
{
    /** @var array  */
    private static $data = [];

    /** @var string  */
    private $namespace;

    public function __construct(string $namespace = 'core')
    {
        $this->namespace = $namespace;

        if (!isset(static::$data[$namespace])) {
            static::$data[$namespace] = [];
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return static::$data[$this->namespace];
    }
    /**
     * @param string $key
     * @return mixed|null
     */
    public function get(string $key, $default = null)
    {
        return aget(static::$data[$this->namespace], $key, $default);
    }

    /**
     * @param string $key
     * @param $value
     * @return Fillable
     */
    public function set(string $key, $value): self
    {
        aset(static::$data[$this->namespace], $key, $value);

        return $this;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key)
    {
        return 'octodummy' !== aget(static::$data[$this->namespace], $key, 'octodummy');
    }

    /**
     * @param string $key
     * @return bool
     */
    public function forget(string $key)
    {
        if ($this->has($key)) {
            adel(static::$data[$this->namespace], $key);

            return true;
        }

        return false;
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
     * @param string $key
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public function __get(string $key)
    {
        return $this->get($key);
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
     * @param string $key
     * @param $value
     * @return Fillable
     */
    public function __set(string $key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        $this->forget($offset);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        return $this->forget($key);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function remove(string $key): bool
    {
        return $this->forget($key);
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     */
    public function increment(string $key, int $by = 1)
    {
        $this->set($key, $value = $this->get($key, 0) + $by);

        return $value;
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     */
    public function decrement(string $key, int $by = 1)
    {
        return $this->increment($key, $by * -1);
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     */
    public function incr(string $key, int $by = 1)
    {
        return $this->increment($key, $by);
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     */
    public function decr(string $key, int $by = 1)
    {
        return $this->increment($key, $by * -1);
    }

    /**
     * @param array|string $key
     * @param null $value
     * @return Fillable
     */
    public function put($key, $value = null): self
    {
        if (!is_array($key) && null === $value) {
            $key = [$key => $value];
        }

        foreach ($key as $arrayKey => $arrayValue) {
            $this->set($arrayKey, $arrayValue);
        }

        return $this;
    }

    /**
     * @param string $key
     * @param callable $c
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function getOr(string $key, callable $c)
    {
        if (!$this->has($key)) {
            $value = callThat($c);

            $this->set($key, $value);

            return $value;
        }

        return $this->get($key);
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->toArray();
    }

    /**
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    /**
     * @return Collection
     */
    public function toCollection()
    {
        return coll($this->toArray());
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     * @throws \ReflectionException
     */
    public function __call(string $name, array $arguments)
    {
        if (fnmatch('get*', $name)) {
            $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($name, 3)));
            $key                = Strings::lower($uncamelizeMethod);
            $args               = [$key];

            if (!empty($arguments)) {
                $args[] = current($arguments);
            }

            return call_user_func_array([$this, 'get'], $args);
        } elseif (fnmatch('set*', $name)) {
            $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($name, 3)));
            $key                = Strings::lower($uncamelizeMethod);

            return $this->set($key, current($arguments));
        } elseif (fnmatch('forget*', $name)) {
            $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($name, 6)));
            $key                = Strings::lower($uncamelizeMethod);
            $args               = [$key];

            return call_user_func_array([$this, 'remove'], $args);
        }

        return $this->toCollection()->{$name}(...$arguments);
    }
}
