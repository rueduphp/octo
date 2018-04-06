<?php
namespace Octo;

class Throttle
{
    private static $instances = [];
    public $resource, $limit, $lifetime, $store;

    /**
     * @param null $store
     * @throws \ReflectionException
     */
    public function __construct($store = null)
    {
        $this->store = $store ?? new Your;
    }

    /**
     * @return array
     */
    public static function getInstances(): array
    {
        return self::$instances;
    }

    /**
     * @param $resource
     * @param int $limit
     * @param int $lifetime
     * @return Throttle
     * @throws \ReflectionException
     */
    public function get($resource, $limit = 50, $lifetime = 30)
    {
        if (!$instance = isAke(static::$instances, $resource, false)) {
            $instance = new self($this->store);

            $instance->setResource($resource)->setLimit($limit)->setLifetime($lifetime);

            self::$instances[$resource] = $instance;

            $this->store->set($resource, 0);
            $this->store->set($resource . '.time', time() + $lifetime);
        }

        return $instance;
    }

    /**
     * @param int $by
     * @throws \ReflectionException
     */
    public function incr($by = 1)
    {
        $this->evaluate();

        $this->store->incr($this->resource, $by);
    }

    /**
     * @param int $by
     * @throws \ReflectionException
     */
    public function decr($by = 1)
    {
        $this->evaluate();

        $this->store->decr($this->resource, $by);
    }

    /**
     * @return bool
     * @throws \ReflectionException
     */
    public function check()
    {
        $check = $this->store->get($this->resource, 0) <= $this->limit;

        $this->evaluate();

        return $check;
    }

    /**
     * @return Your
     * @throws \ReflectionException
     */
    public function clear()
    {
        $this->store->set($this->resource . '.time', time() + $this->lifetime);

        return $this->store->set($this->resource, 0);
    }

    /**
     * @param int $by
     * @return bool
     * @throws \ReflectionException
     */
    public function attempt($by = 1)
    {
        $this->incr($by);

        return $this->check();
    }

    /**
     * @return Your
     * @throws \ReflectionException
     */
    private function evaluate()
    {
        $when = $this->store->get($this->resource . '.time');

        if ($when < time()) {
            $this->store->set($this->resource . '.time', time() + $this->lifetime);

            return $this->store->set($this->resource, 0);
        }
    }

    /**
     * @return mixed
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @return mixed
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @return null|Your
     */
    public function getStore(): ?Your
    {
        return $this->store;
    }

    /**
     * @return mixed
     */
    public function getLifetime()
    {
        return $this->lifetime;
    }

    /**
     * @param mixed $resource
     * @return Throttle
     */
    public function setResource($resource)
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * @param mixed $limit
     * @return Throttle
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * @param mixed $lifetime
     * @return Throttle
     */
    public function setLifetime($lifetime)
    {
        $this->lifetime = $lifetime;

        return $this;
    }

    /**
     * @param null|Your $store
     * @return Throttle
     */
    public function setStore(?Your $store): Throttle
    {
        $this->store = $store;

        return $this;
    }

    /**
     * @return int
     * @throws \ReflectionException
     */
    public function getAttempts(): int
    {
        return $this->store->get($this->resource, 0);
    }
}
