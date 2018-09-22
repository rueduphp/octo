<?php
namespace App\Traits;

trait Gettable
{
    use Itemable;

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
}
