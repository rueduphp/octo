<?php
namespace App\Services;

use ArrayAccess;
use Illuminate\Redis\RedisManager;

class Stack implements ArrayAccess
{
    /** @var RedisManager */
    protected $store;

    /**
     * @var string
     */
    private $scope;

    /**
     * @var int
     */
    private $ttl;

    /**
     * @param string $scope
     * @param int $ttl
     */
    public function __construct(string $scope = 'core', int $ttl = 3600)
    {
        $this->scope = $scope;
        $this->ttl = $ttl;
    }

    /**
     * @param int $ttl
     * @return Stack
     */
    public function setTtl(int $ttl): self
    {
        $this->ttl = $ttl;

        return $this;
    }

    /**
     * @param string $scope
     * @return Stack
     */
    public function setScope(string $scope): self
    {
        $this->scope = $scope;

        return $this;
    }


    /**
     * @param string $key
     * @return array
     */
    protected function getData(string $key)
    {
        $key = $this->makeKey($key);

        $store = $this->getStore();

        if (0 < $store->exists($key)) {
            return unserialize($store->get($key));
        }

        return [];
    }

    /**
     * @param string $key
     * @param array $data
     * @return Stack
     */
    protected function setData(string $key, array $data): self
    {
        $key = $this->makeKey($key);

        $store = $this->getStore();
        $store->set($key, serialize($data));

        $store->expire($key, $this->ttl);

        return $this;
    }

    /**
     * @param string $offset
     * @return array
     */
    protected function getIdentifiers(string $offset)
    {
        $parts = explode('.', $offset);

        $key = array_shift($parts);

        return [$key, !empty($parts) ? implode('.', $parts) : null];
    }

    public function offsetExists($offset)
    {
        list($key, $id) = $this->getIdentifiers($offset);

        $data = $this->getData($key);

        return (null !== $id) ? (null !== array_get($data, $id)) : !empty($data);
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        list($key, $id) = $this->getIdentifiers($offset);
        $data = $this->getData($key);

        return (null !== $id) ? array_get($data, $id) : current($data);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        list($key, $id) = $this->getIdentifiers($offset);
        $data = $this->getData($key);

        if (null !== $id) {
            $data = array_set($data, $id, $value);
        } else {
            $data = [$value];
        }

        $this->setData($key, $data);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        list($key, $id) = $this->getIdentifiers($offset);
        $data = $this->getData($key);

        if (null !== $id) {
            array_forget($data, $id);
        } else {
            $data = [];
        }

        $this->setData($key, $data);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function __set($offset, $value)
    {
        $this->offsetSet($offset, $value);
    }

    /**
     * @param  mixed $offset
     * @return mixed
     */
    public function __get($offset)
    {
        return $this->offsetGet($offset);
    }

    /**
     * @param  mixed   $offset
     * @return bool
     */
    public function __isset($offset)
    {
        return $this->offsetExists($offset);
    }

    /**
     * @param mixed $offset
     */
    public function __unset($offset)
    {
        $this->offsetUnset($offset);
    }

    /**
     * @return RedisManager
     */
    public function getStore()
    {
        return $this->store ?? $this->store = l('redis');
    }

    /**
     * @param $store
     * @return Stack
     */
    public function setStore($store): self
    {
        $this->store = $store;

        return $this;
    }

    /**
     * @param string $key
     * @return string
     */
    protected function makeKey(string $key)
    {
        return $this->scope . '.' . $key;
    }
}
