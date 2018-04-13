<?php
namespace Octo;

use Closure;

class Iterator implements \ArrayAccess, \Iterator, ToArray
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
     * @var callable|null
     */
    private $transformer;

    public function __construct($records, ?callable $transformer = null)
    {
        $records = arrayable($records) ? $records->toArray() : $records;

        $this->records = $records;

        if (null === $transformer) {
            $transformer = function ($row) {
                return $row;
            };
        }

        $this->transformer = $transformer;
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
                $params = [$this->transformer, $actual];

                $result = gi()->makeClosure(...$params);
            } elseif (is_array($this->transformer)) {
                $params = array_merge($this->transformer, [$actual]);

                $result = gi()->call(...$params);
            } else {
                $result = gi()->call($this->transformer, '__invoke', $actual);
            }

            $this->changedRecords[$index] = $result;
        }

        return $this->changedRecords[$index];
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
     * @throws \Exception
     */
    public function offsetSet($offset, $value)
    {
        throw new \Exception("Can't alter records");
    }

    /**
     * @param mixed $offset
     * @throws \Exception
     */
    public function offsetUnset($offset)
    {
        throw new \Exception("Can't alter records");
    }

    /**
     * @return array
     * @throws \ReflectionException
     */
    public function toArray(): array
    {
        $records = [];

        foreach ($this->records as $k => $v) {
            $records[] = $this->get($k);
        }

        return $records;
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
    public function setTransformer(callable $transformer): self
    {
        $this->transformer = $transformer;

        return $this;
    }

    /**
     * @return Collection
     * @throws \ReflectionException
     */
    public function toCollection()
    {
        return coll($this->toArray());
    }

    /**
     * @param string $method
     * @param array $parameters
     * @return mixed|null|Iterator
     * @throws \ReflectionException
     */
    public function __call(string $method, array $parameters)
    {
        $collection = $this->toCollection();

        $params = array_merge([$collection, $method], $parameters);

        $result = gi()->call(...$params);

        return $result instanceof Collection
            ? new self($result->all())
            : $result
        ;
    }
}
