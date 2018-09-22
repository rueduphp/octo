<?php
namespace App\Traits;

trait Itemable
{
    /**
     * @param mixed $offset  An offset to check for.
     * @return bool          Returns TRUE on success or FALSE on failure.
     */
    public function offsetExists($offset)
    {
        return isset($this->__items[$offset]);
    }

    /**
     * @param mixed $offset  The offset to retrieve.
     * @return mixed         Can return all value types.
     */
    public function offsetGet($offset)
    {
        return isset($this->__items[$offset]) ? $this->__items[$offset] : null;
    }

    /**
     * @param mixed $offset  The offset to assign the value to.
     * @param mixed $value   The value to set.
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->__items[] = $value;
        } else {
            $this->__items[$offset] = $value;
        }
    }

    /**
     * @param mixed $offset  The offset to unset.
     */
    public function offsetUnset($offset)
    {
        unset($this->__items[$offset]);
    }
}
