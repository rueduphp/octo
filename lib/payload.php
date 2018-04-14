<?php
namespace Octo;

use Closure;

class Payload implements \ArrayAccess, \Iterator
{
    /**
     * @var array
     */
    private $records;

    /**
     * @var int
     */
    private $index = 0;

    /**
     * @var array
     */
    private $changedRecords = [];

    /**
     * @var callable
     */
    private $encoder;

    public function __construct($records, callable $encoder)
    {
        $records = arrayable($records) ? $records->toArray() : $records;

        $this->records = $records;

        $this->encoder = $encoder;
    }

    /**
     * @param int $index
     *
     * @return mixed
     *
     * @throws \ReflectionException
     */
    public function get(int $index)
    {
        if (!isset($this->changedRecords[$index])) {
            $actual = $this->records[$index];

            if ($this->transformer instanceof Closure) {
                $params = [$this->encoder, $actual];

                $result = gi()->makeClosure(...$params);
            } elseif (is_array($this->encoder)) {
                $params = array_merge($this->encoder, [$actual]);

                $result = gi()->call(...$params);
            } else {
                $result = gi()->call($this->encoder, '__invoke', $actual);
            }

            $this->changedRecords[$index] = $result;
        }

        return isset($this->changedRecords[$index]) ? $this->changedRecords[$index] : null;
    }

    /**
     * @return mixed
     * @throws \ReflectionException
     */
    public function current()
    {
        return $this->get($this->index);
    }

    /**
     * @param null $default
     *
     * @return mixed|null
     *
     * @throws \ReflectionException
     */
    public function first($default = null)
    {
        return $this->count() > 0 ? $this->get(0) : $default;
    }

    /**
     * @return mixed
     *
     * @throws \ReflectionException
     */
    public function last($default = null)
    {
        $count = $this->count();

        return $count > 0 ? $this->get($count - 1) : $default;
    }

    public function next(): void
    {
        $this->index++;
    }

    /**
     * @return int|mixed
     */
    public function key()
    {
        return $this->index;
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return isset($this->records[$this->index]);
    }

    public function rewind()
    {
        $this->index = 0;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->records[$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed
     * @throws \ReflectionException
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->records[$offset] = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->records[$offset]);
    }

    /**
     * @return string
     * @throws \ReflectionException
     */
    public function __toString(): string
    {
        if ($this->transformer instanceof Closure) {
            $params = [$this->encoder, $this->records];

            $result = gi()->makeClosure(...$params);
        } elseif (is_array($this->encoder)) {
            $params = array_merge($this->encoder, [$this->records]);

            $result = gi()->call(...$params);
        } else {
            $result = gi()->call($this->encoder, '__invoke', $this->records);
        }

        return $result;
    }

    public function count()
    {
        return count($this->records);
    }

    /**
     * @param callable $transformer
     *
     * @return Iterator
     */
    public function setEncoder(callable $encoder): self
    {
        $this->encoder = $encoder;

        return $this;
    }
}
