<?php
namespace Octo;

class Your implements \ArrayAccess
{
    /** @var Your */
    protected static $instance;

    /** @var string */
    protected static $token;

    /** @var Caching */
    public $store;

    /**
     * @param string $store
     * @throws \ReflectionException
     */
    public function __construct($store = Caching::class)
    {
        static::$token = You::init();

        $this->store = new $store('your.' . static::$token);
    }

    /**
     * @param array $data
     * @return Your
     * @throws \ReflectionException
     */
    public static function fill(array $data): self
    {
        foreach ($data as $key => $value) {
            $self = static::set($key, $value);
        }

        return $self;
    }

    /**
     * @param string $key
     * @param $value
     * @return Your
     * @throws \ReflectionException
     */
    public static function set(string $key, $value): self
    {
        $self = static::called();
        $self->store->set($key, $value);

        return $self;
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed|null
     * @throws \ReflectionException
     */
    public static function get(string $key, $default = null)
    {
        $self = static::called();

        return $self->store->get($key, $default);
    }

    /**
     * @param string $key
     * @return bool
     * @throws \ReflectionException
     */
    public static function has(string $key): bool
    {
        $self = static::called();

        return $self->store->has($key);
    }

    /**
     * @param string $key
     * @return bool
     * @throws \ReflectionException
     */
    public static function delete(string $key): bool
    {
        $self = static::called();

        $status = $self::has($key);

        $self->store->delete($key);

        return $status;
    }

    /**
     * @param string $key
     * @return bool
     * @throws \ReflectionException
     */
    public static function remove(string $key): bool
    {
        return static::delete($key);
    }

    /**
     * @param string $key
     * @return bool
     * @throws \ReflectionException
     */
    public static function forget(string $key): bool
    {
        return static::delete($key);
    }

    /**
     * @param string $key
     * @return bool
     * @throws \ReflectionException
     */
    public static function del(string $key): bool
    {
        return static::delete($key);
    }

    /**
     * @return Your
     * @throws \ReflectionException
     */
    public static function getInstance()
    {
        return static::called();
    }

    /**
     * @return Your
     *
     * @throws \ReflectionException
     */
    public static function called()
    {
        if (is_null(static::$instance)) {
            static::$instance = gi()->singleton(get_called_class());
        }

        return static::$instance;
    }

    /**
     * @param mixed $offset
     * @return bool
     * @throws \ReflectionException
     */
    public function offsetExists($offset)
    {
        $self = static::called();

        return $self::has($offset);
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function offsetGet($offset)
    {
        $self = static::called();

        return $self::get($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @return void
     * @throws \ReflectionException
     */
    public function offsetSet($offset, $value)
    {
        $self = static::called();

        $self::set($offset, $value);
    }

    /**
     * @param mixed $offset
     * @return void
     * @throws \ReflectionException
     */
    public function offsetUnset($offset)
    {
        $self = static::called();

        $self::delete($offset);
    }

    /**
     * @param string $key
     * @param $value
     * @return Your
     * @throws \ReflectionException
     */
    public function __set(string $key, $value)
    {
        return static::called()->set($key, $value);
    }

    /**
     * @param string $key
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function __get(string $key)
    {
        return static::called()->get($key);
    }

    /**
     * @param string $key
     * @return bool
     * @throws \ReflectionException
     */
    public function __isset(string $key)
    {
        return static::called()->has($key);
    }

    /**
     * @param string $key
     * @return bool
     * @throws \ReflectionException
     */
    public function __unset(string $key)
    {
        return static::called()->delete($key);
    }

    /**
     * @param $name
     * @param $arguments
     * @return Your|mixed
     * @throws \ReflectionException
     */
    public static function __callStatic($name, $arguments)
    {
        $self = static::called();

        if (fnmatch('get*', $name)) {
            $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($name, 3)));
            $key                = Strings::lower($uncamelizeMethod);
            $args               = [$key];

            if (!empty($arguments)) {
                $args[] = current($arguments);
            }

            return static::called()->get(...$args);
        } elseif (fnmatch('set*', $name)) {
            $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($name, 3)));
            $key                = Strings::lower($uncamelizeMethod);

            return static::called()->set($key, current($arguments));
        } elseif (fnmatch('forget*', $name)) {
            $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($name, 6)));
            $key                = Strings::lower($uncamelizeMethod);

            return static::called()->delete($key);
        } elseif (fnmatch('has*', $name)) {
            $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($name, 3)));
            $key                = Strings::lower($uncamelizeMethod);

            return static::called()->has($key);
        }

        $value = $self->store->{$name}(...$arguments);

        $class = get_class($self->store);

        return ($value instanceof $class) ? $self : $value;
    }

    /**
     * @param $m
     * @param $a
     * @return bool|mixed|null|Your
     * @throws \ReflectionException
     */
    public function __call($m, $a)
    {
        if (fnmatch('get*', $m)) {
            $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($m, 3)));
            $key                = Strings::lower($uncamelizeMethod);
            $args               = [$key];

            if (!empty($a)) {
                $args[] = current($a);
            }

            return static::called()->get(...$args);
        } elseif (fnmatch('set*', $m)) {
            $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($m, 3)));
            $key                = Strings::lower($uncamelizeMethod);

            return static::called()->set($key, current($a));
        } elseif (fnmatch('forget*', $m)) {
            $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($m, 6)));
            $key                = Strings::lower($uncamelizeMethod);

            return static::called()->delete($key);
        } elseif (fnmatch('has*', $m)) {
            $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($m, 3)));
            $key                = Strings::lower($uncamelizeMethod);

            return static::called()->has($key);
        }

        $self = static::called();

        $value = $self->store->{$m}(...$a);

        $class = get_class($self->store);

        return ($value instanceof $class) ? $self : $value;
    }
}