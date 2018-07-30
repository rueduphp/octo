<?php
namespace App\Traits;

use function Octo\aget;

trait Attributable
{
    /** @var array  */
    protected $__attributes = [];

    /**
     * @param string $key
     * @return mixed|null
     */
    public function __get($key)
    {
        return $this->getAttribute($key) ?? aget($this->__attributes, $key);
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function offsetGet($key)
    {
        return $this->getAttribute($key) ?? aget($this->__attributes, $key);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function __isset($key)
    {
        $exists = array_key_exists($key, $this->getAttributes()) || $this->hasGetMutator($key);

        if (true === $exists) {
            return !is_null($this->getAttribute($key));
        }

        return 'octodummy' !== aget($this->__attributes, $key, 'octodummy');
    }

    /**
     * @param string $key
     * @return bool
     */
    public function offsetExists($key)
    {
        $exists = array_key_exists($key, $this->getAttributes()) || $this->hasGetMutator($key);

        if (true === $exists) {
            return !is_null($this->getAttribute($key));
        }

        return 'octodummy' !== aget($this->__attributes, $key, 'octodummy');
    }

    /**
     * @param string $key
     * @return bool
     */
    public function __unset($key)
    {
        $exists = array_key_exists($key, $this->getAttributes()) || $this->hasGetMutator($key);

        if (true === $exists) {
            unset($this->attributes[$key], $this->relations[$key]);
        } else {
            unset($this->__attributes[$key]);
        }
    }

    /**
     * @param string $key
     * @return bool
     */
    public function offsetUnset($key)
    {
        $exists = array_key_exists($key, $this->getAttributes()) || $this->hasGetMutator($key);

        if (true === $exists) {
            unset($this->attributes[$key], $this->relations[$key]);
        } else {
            unset($this->__attributes[$key]);
        }
    }

    /**
     * @param string $key
     * @param $value
     * @return $this
     */
    public function __set($key, $value)
    {
        $exists = array_key_exists($key, $this->getAttributes()) || $this->hasGetMutator($key);

        if (true === $exists) {
            return $this->setAttribute($key, $value);
        }

        $this->__attributes[$key] = $value;

        return $this;
    }

    /**
     * @param string $key
     * @param $value
     * @return $this
     */
    public function offsetSet($key, $value)
    {
        $exists = array_key_exists($key, $this->getAttributes()) || $this->hasGetMutator($key);

        if (true === $exists) {
            return $this->setAttribute($key, $value);
        }

        $this->__attributes[$key] = $value;

        return $this;
    }
}
