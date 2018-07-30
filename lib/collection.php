<?php
namespace Octo;

use Countable;
use ArrayAccess;
use ArrayIterator;
use CachingIterator;
use Illuminate\Support\Debug\Dumper;
use JsonSerializable;
use IteratorAggregate;

class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    use Macroable;

    protected $items = [];

    public function __construct($items = [])
    {
        if (is_string($items)) {
            $items = [$items];
        }

        $items = is_null($items) ? [] : $this->getArrays($items);

        $this->items = (array) $items;
    }

    public function create($collec = false)
    {
        $coll = [];

        if (!empty($this->items)) {
            $first = current($this->items);

            if ($first instanceof Object) {
                foreach ($this->items as $item) {
                    if ($item->hasModel()) {
                        $row = $item->save();
                        $coll[] = $row;
                    }
                }
            }
        }

        return $collec ? $this->new($coll) : count($coll);
    }

    public function lastFake()
    {
        if (!empty($this->items)) {
            $end = end($this->items);

            if ($end instanceof Object) {
                if ($end->hasModel()) {
                    return $end->save();
                }
            }
        }

        return null;
    }

    /**
     * @param array $items
     * @return Collection
     */
    public static function make($items = [])
    {
        return new static($items);
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->items;
    }

    /**
     * @return Collection
     */
    public function collapse()
    {
        $results = [];

        foreach ($this->items as $values) {
            if ($values instanceof self) $values = $values->all();

            $results = array_merge($results, $values);
        }

        return $this->new($results);
    }

    /**
     * @param $key
     * @param null $value
     * @return bool
     */
    public function contains($key, $operator = null, $value = null)
    {
        if (func_num_args() === 1) {
            if ($this->useAsCallable($key)) {
                $placeholder = new \stdClass;

                return $this->first($key, $placeholder) !== $placeholder;
            }

            return in_array($key, $this->items);
        }

        if (func_num_args() === 2) {
            $value = $operator;

            $operator = '=';
        }

        return $this->contains($this->closureWhere($key, $operator, $value));
    }

    /**
     * @param $key
     * @param null $value
     * @return bool
     */
    public function containsStrict($key, $value = null)
    {
        if (func_num_args() == 2) {
            return $this->contains(function ($item) use ($key, $value) {
                return dget($item, $key) === $value;
            });
        }

        if ($this->isClosure($key)) {
            return !is_null($this->first($key));
        }

        return in_array($key, $this->items, true);
    }

    /**
     * @return Collection
     */
    public function diff($items)
    {
        return $this->new(array_diff($this->items, $this->getArrays($items)));
    }

    /**
     * @return Collection
     */
    public function diffKeys($items)
    {
        return $this->new(array_diff_key($this->items, $this->getArrays($items)));
    }

    /**
     * @return Collection
     */
    public function each(callable $callback)
    {
        array_map($callback, $this->items);

        return $this;
    }

    /**
     * @param string $key
     * @return Collection
     */
    public function fetch($key)
    {
        return $this->new(Arrays::fetchOne($this->items, $key));
    }

    /**
     * @return Collection
     */
    public function step($step, $offset = 0)
    {
        $new = [];

        $position = 0;

        foreach ($this->items as $item) {
            if ($position % $step === $offset) {
                $new[] = $item;
            }

            $position++;
        }

        return $this->new($new);
    }

    /**
     * @return Collection
     */
    public function except(...$keys)
    {
        return $this->new(Arrays::except($this->items, $keys));
    }

    /**
     * @return Collection
     */
    public function filter(callable $callback)
    {
        return new static(array_filter($this->items, $callback));
    }

    /**
     * @param $key
     * @param $value
     * @return Collection
     */
    public function whereLoose($key, $value)
    {
        return $this->where($key, $value, false);
    }

    /**
     * @param $key
     * @param $values
     * @param bool $strict
     * @return Collection
     */
    public function whereIn($key, $values, $strict = false)
    {
        $values = $this->getArrays($values);

        return $this->filter(function ($item) use ($key, $values, $strict) {
            return in_array(dget($item, $key), $values, $strict);
        });
    }

    /**
     * @param $key
     * @param $values
     * @return Collection
     */
    public function whereInStrict($key, $values)
    {
        return $this->whereIn($key, $values, true);
    }

    public function first(callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            return !empty($this->items) ? reset($this->items) : $default;
        }

        return Arrays::firstOne($this->items, $callback, $default);
    }

    /**
     * @return Collection
     */
    public function flatten()
    {
        return $this->new(Arrays::flatten($this->items));
    }

    /**
     * @return Collection
     */
    public function flip()
    {
        return $this->new(array_flip($this->items));
    }

    public function forget($key)
    {
        $this->offsetUnset($key);
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function get(string $key, $default = null)
    {
        if ($this->offsetExists($key)) {
            return $this->items[$key];
        }

        return value($default);
    }

    /**
     * @return Collection
     */
    public function groupBy($groupBy)
    {
        if (!$this->isClosure($groupBy)) {
            return $this->groupBy($this->makeClosure($groupBy));
        }

        $results = [];

        foreach ($this->items as $key => $value) {
            $groupKey = $groupBy($value, $key);

            if (!array_key_exists($groupKey, $results)) {
                $results[$groupKey] = $this->new([]);
            }

            $results[$groupKey]->push($value);
        }

        return $this->new($results);
    }

    /**
     * @param $field
     * @return mixed
     */
    public function min($field)
    {
        $row = $this->sortBy($field)->first();

        return isAke($row, $field, 0);
    }

    public function max($field)
    {
        $row = $this->sortByDesc($field)->first();

        return isAke($row, $field, 0);
    }

    /**
     * @return Collection
     */
    public function keyBy($keyBy)
    {
        if (!$this->isClosure($keyBy)) {
            return $this->keyBy($this->makeClosure($keyBy));
        }

        $results = [];

        foreach ($this->items as $key => $item) {
            $resolvedKey = $keyBy($item, $key);

            if (is_object($resolvedKey)) {
                $resolvedKey = (string) $resolvedKey;
            }

            $results[$resolvedKey] = $item;
        }

        return new static($results);
    }

    /**
     * @return Collection
     */
    public function between($field = 'id', $min = 0, $max = 0)
    {
        if (!$this->isClosure($field)) {
            return $this->between($this->makeClosure($field), $min, $max);
        }

        $results = [];

        foreach ($this->items as $key => $value) {
            $val = $field($value);

            if ($val >= $min && $val <= $max) {
                $results[] = $value;
            }
        }

        return new static($results);
    }

    /**
     * @param $key
     * @return bool
     */
    public function has($key)
    {
        return $this->offsetExists($key);
    }

    /**
     * @param $value
     * @param null $glue
     * @return string
     */
    public function implode($value, $glue = null)
    {
        $first = $this->first();

        if (is_array($first) || is_object($first)) {
            return implode($glue, $this->pluck($value)->all());
        }

        return implode($value, $this->items);
    }

    /**
     * @return Collection
     */
    public function intersect($items)
    {
        return new static(array_intersect($this->items, $this->getArrays($items)));
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->items);
    }

    /**
     * @param $value
     * @return bool
     */
    protected function isClosure($value)
    {
        return !is_string($value) && is_callable($value);
    }

    /**
     * @return Collection
     */
    public function union($items)
    {
        return new static($this->items + $this->getArrays($items));
    }

    /**
     * @return Collection
     */
    public function keys()
    {
        return new static(array_keys($this->items));
    }

    /**
     * @param null $default
     * @return mixed|null
     */
    public function last($default = null)
    {
        return !empty($this->items) ? end($this->items) : $default;
    }

    /**
     * @param $value
     * @param null $key
     * @return array
     */
    public function lists($value, $key = null)
    {
        return Arrays::pluck($this->items, $value, $key);
    }

    /**
     * @param $value
     * @param null $key
     * @return array
     */
    public function pluck($value, $key = null)
    {
        return Arrays::pluck($this->items, $value, $key);
    }

    /**
     * @return Collection
     */
    public function map(callable $callback)
    {
        $keys = array_keys($this->items);

        $items = array_map($callback, $this->items, $keys);

        return new static(array_combine($keys, $items));
    }

    public function mapSpread(callable $callback)
    {
        return $this->map(function ($chunk, $key) use ($callback) {
            $chunk[] = $key;

            return $callback(...$chunk);
        });
    }

    public function mapToDictionary(callable $callback)
    {
        $dictionary = $this->map($callback)->reduce(function ($groups, $pair) {
            $groups[key($pair)][] = reset($pair);

            return $groups;
        }, []);

        return new static($dictionary);
    }

    public function mapToGroups(callable $callback)
    {
        $groups = $this->mapToDictionary($callback);

        return $groups->map([$this, 'make']);
    }

    /**
     * @return Collection
     */
    public function mapWithKeys(callable $callback)
    {
        $result = [];

        foreach ($this->items as $key => $value) {
            $assoc = $callback($value, $key);

            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }

        return new static($result);
    }

    /**
     * @return Collection
     */
    public function flatMap(callable $callback)
    {
        return $this->map($callback)->collapse();
    }

    /**
     * @return mixed
     * @throws \ReflectionException
     */
    public function only()
    {
        $keys = func_get_args();

        if (empty($keys)) {
            return $this->new($this->items);
        }

        return $this->new(Arrays::only($this->items, $keys));
    }

    /**
     * @param $items
     * @return Collection
     */
    public function merge($items)
    {
        return $this->new(array_merge($this->items, $this->getArrays($items)));
    }

    /**
     * @param $page
     * @param $perPage
     * @return mixed
     * @throws \ReflectionException
     */
    public function forPage($page, $perPage)
    {
        return $this->new(array_slice($this->items, ($page - 1) * $perPage, $perPage));
    }

    public function pop()
    {
        return array_pop($this->items);
    }

    public function prepend($value)
    {
        array_unshift($this->items, $value);
    }

    public function push($value)
    {
        $this->offsetSet(null, $value);
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public function pull(string $key, $default = null)
    {
        return Arrays::pull($this->items, $key, $default);
    }

    /**
     * @param string $key
     * @param $value
     */
    public function put(string $key, $value)
    {
        $this->offsetSet($key, $value);
    }

    /**
     * @param int $amount
     * @return array|mixed
     */
    public function random(int $amount = 1)
    {
        if ($this->isEmpty()) {
            return;
        }

        $keys = array_rand($this->items, $amount);

        return is_array($keys)
        ? array_intersect_key($this->items, array_flip($keys))
        : $this->items[$keys];
    }

    /**
     * @param callable $callback
     * @param null $initial
     * @return mixed
     */
    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * @param $callback
     * @return Collection
     */
    public function reject($callback)
    {
        if ($this->isClosure($callback)) {
            return $this->filter(function($item) use ($callback) {
                return !$callback($item);
            });
        }

        return $this->filter(function($item) use ($callback) {
            return $item !== $callback;
        });
    }

    /**
     * @return mixed
     * @throws \ReflectionException
     */
    public function reverse()
    {
        return $this->new(array_reverse($this->items, true));
    }

    public function search($value, $strict = false)
    {
        if (!$this->isClosure($value)) {
            return array_search($value, $this->items, $strict);
        }

        foreach ($this->items as $key => $item) {
            if (call_user_func($value, $item, $key)) {
                return $key;
            }
        }

        return false;
    }

    public function shift()
    {
        return array_shift($this->items);
    }

    /**
     * @return mixed
     * @throws \ReflectionException
     */
    public function shuffle()
    {
        $items = $this->items;

        shuffle($items);

        return $this->new($items);
    }

    /**
     * @return mixed
     * @throws \ReflectionException
     */
    public function sortByRand()
    {
        $items = $this->items;

        shuffle($items);

        return $this->new($items);
    }

    /**
     * @param $offset
     * @param null $length
     * @return mixed
     * @throws \ReflectionException
     */
    public function slice($offset, $length = null)
    {
        return $this->new(array_slice($this->items, $offset, $length, true));
    }

    /**
     * @param $size
     * @return mixed
     * @throws \ReflectionException
     */
    public function chunk($size)
    {
        $chunks = [];

        foreach (array_chunk($this->items, $size, true) as $chunk) {
            $chunks[] = $this->new($chunk);
        }

        return $this->new($chunks);
    }

    /**
     * @param callable|null $callback
     * @return mixed
     * @throws \ReflectionException
     */
    public function sort(callable $callback = null)
    {
        $items = $this->items;

        $callback ? uasort($items, $callback) : uasort($items, function ($a, $b) {
            if ($a == $b) {
                return 0;
            }

            return ($a < $b) ? -1 : 1;
        });

        return $this->new($items);
    }

    /**
     * @param $callback
     * @param int $options
     * @param bool $descending
     * @return Collection
     */
    public function sortBy($callback, $options = SORT_REGULAR, $descending = false)
    {
        $results = [];

        $callback = $this->makeClosure($callback);

        foreach ($this->items as $key => $value) {
            $results[$key] = $callback($value, $key);
        }

        $descending
            ? arsort($results, $options)
            : asort($results, $options)
        ;

        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }

        return new static($results);
    }

    /**
     * @param $callback
     * @param int $options
     * @return Collection
     */
    public function sortByDesc($callback, $options = SORT_REGULAR)
    {
        return $this->sortBy($callback, $options, true);
    }

    /**
     * @param $offset
     * @param null $length
     * @param array $replacement
     * @return mixed
     * @throws \ReflectionException
     */
    public function splice($offset, $length = null, $replacement = [])
    {
        if (func_num_args() === 1) {
            return $this->new(array_splice($this->items, $offset));
        }

        return $this->new(array_splice($this->items, $offset, $length, $replacement));
    }

    /**
     * @param null $callback
     * @return float|int|mixed
     */
    public function sum($callback = null)
    {
        if (is_null($callback)) {
            return array_sum($this->items);
        }

        if (!$this->isClosure($callback)) {
            $callback = $this->makeClosure($callback);
        }

        return $this->reduce(function($result, $item) use ($callback) {
            return $result += $callback($item);
        }, 0);
    }

    public function avg($field = null)
    {
        if ($count = $this->count()) {
            return (double) $this->sum($field) / $count;
        }

        return 0;
    }

    /**
     * @param null $field
     * @return float|int
     */
    public function average($field = null)
    {
        return $this->avg($field);
    }

    /**
     * @param null $limit
     * @return mixed
     * @throws \ReflectionException
     */
    public function take($limit = null)
    {
        if ($limit < 0) {
            return $this->slice($limit, abs($limit));
        }

        return $this->slice(0, $limit);
    }

    public function transform(callable $callback)
    {
        $this->items = array_map($callback, $this->items);

        return $this;
    }

    /**
     * @param null $key
     * @return Collection
     * @throws \ReflectionException
     */
    public function unique($key = null)
    {
        if (is_null($key)) {
            return $this->new(array_unique($this->items, SORT_REGULAR));
        }

        $key = $this->makeClosure($key);

        $exists = [];

        return $this->reject(function ($item) use ($key, &$exists) {
            if (in_array($id = $key($item), $exists)) {
                return true;
            }

            $exists[] = $id;
        });
    }

    /**
     * @return mixed
     * @throws \ReflectionException
     */
    public function values()
    {
        return $this->new(array_values($this->items));
    }

    public function avalues()
    {
        return array_values($this->items);
    }

    /**
     * @param $value
     *
     * @return \Closure
     */
    protected function makeClosure($value)
    {
        if ($this->useAsCallable($value)) {
            return $value;
        }

        return function ($item) use ($value) {
            return dget($item, $value);
        };
    }

    protected function useAsCallable($value)
    {
        return !is_string($value) && is_callable($value);
    }

    /**
     * @return mixed
     * @throws \ReflectionException
     */
    public function zip()
    {
        $arrayableItems = array_map(function ($items) {
            return $this->getArrays($items);
        }, func_get_args());

        $params = array_merge([function () {
            return $this->new(func_get_args());
        }, $this->items], $arrayableItems);

        return $this->new(call_user_func_array('array_map', $params));
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return array_map(function($value) {
            return arrayable($value) ? $value->toArray() : $value;
        }, $this->items);
    }

    /**
     * @return array
     */
    public function items()
    {
        return array_map(function($value) {
            $row = arrayable($value) ? $value->toArray() : $value;

            return item($row);
        }, $this->items);
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * @param int $options
     *
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * @return ArrayIterator|\Traversable
     */
    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @param int $flags
     *
     * @return CachingIterator
     */
    public function getCachingIterator($flags = CachingIterator::CALL_TOSTRING)
    {
        return new CachingIterator($this->getIterator(), $flags);
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->items);
    }

    /**
     * @param mixed $key
     *
     * @return bool
     */
    public function offsetExists($key)
    {
        return array_key_exists($key, $this->items);
    }

    public function offsetGet($key)
    {
        return $this->items[$key];
    }

    /**
     * @param $value
     *
     * @return $this
     */
    public function add($value)
    {
        $this->items[] = $value;

        return $this;
    }

    public function offsetSet($key, $value)
    {
        if (is_null($key)) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    public function offsetUnset($key)
    {
        unset($this->items[$key]);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    public function nth($key, $d = null)
    {
        return isset($this->items[$key]) ? $this->items[$key] : $d;
    }

    /**
     * @param $items
     *
     * @return array
     */
    protected function getArrays($items)
    {
        if (arrayable($items)) {
            $items = $items->toArray();
        }

        return $items;
    }

    public function like($field, $value)
    {
        return $this->where($field, 'like', $value);
    }

    public function notLike($field, $value)
    {
        return $this->where($field, 'not like', $value);
    }

    public function findBy($field, $value)
    {
        return $this->where($field, '=', $value);
    }

    public function find($field, $value = null)
    {
        $field = empty($value) ? 'id' : $field;
        $value = empty($value) ? $field : $value;

        return $this->where($field, '=', $value)->first();
    }

    public function firstBy($field, $value)
    {
        return $this->where($field, '=', $value)->first();
    }

    public function lastBy($field, $value)
    {
        return $this->where($field, '=', $value)->last();
    }

    public function in($field, array $values)
    {
        return $this->where($field, 'in', $values);
    }

    public function notIn($field, array $values)
    {
        return $this->where($field, 'not in', $values);
    }

    public function rand($default = null)
    {
        if (!empty($this->items)) {
            shuffle($this->items);

            return current($this->items);
        }

        return $default;
    }

    public function isBetween($field, $min, $max)
    {
        return $this->where($field, 'between', [$min, $max]);
    }

    public function isNotBetween($field, $min, $max)
    {
        return $this->where($field, 'not between', [$min, $max]);
    }

    public function isNull($field)
    {
        return $this->where($field, 'is', 'null');
    }

    public function isNotNull($field)
    {
        return $this->where($field, 'is not', 'null');
    }

    /**
     * @param string $key
     * @param mixed $type
     * @return Collection
     */
    public function is(string $key, $type)
    {
        return $this->where(function ($item) use ($key, $type) {
            $value = $item[$key] ?? null;

            return $type === $value;
        });
    }

    /**
     * @param string $key
     * @return Collection
     */
    public function isTrue(string $key)
    {
        return $this->is($key, true);
    }

    /**
     * @param string $key
     * @return Collection
     */
    public function isFalse(string $key)
    {
        return $this->is($key, false);
    }

    /**
     * @param string $key
     * @return Collection
     */
    public function hasEmptyValue(string $key)
    {
        return $this->where(function ($item) use ($key) {
            $value = $item[$key] ?? null;

            return empty($value);
        });
    }

    /**
     * @param $key
     * @param null|string $operator
     * @param null $value
     * @return Collection
     */
    public function where($key, ?string $operator = null, $value = null): self
    {
        $isCallable = false;

        if (func_num_args() === 1) {
            if (is_callable($key)) {
                $isCallable = true;
            }

            if (is_array($key) && false === $isCallable) {
                list($key, $operator, $value) = $key;
                $operator = Inflector::lower($operator);
            }
        } elseif (func_num_args() === 2) {
            list($value, $operator) = [$operator, '='];
        }

        if (true === $isCallable) {
            return $this->filter(function($item) use ($key) {
                if ($key instanceof \Closure) {
                    return gi()->makeClosure($key, $item);
                } elseif (is_array($key)) {
                    $params = array_merge($key, $item);

                    return gi()->call(...$params);
                } else {
                    $params = array_merge([$key, '__invoke'], $item);

                    return gi()->call(...$params);
                }
            });
        }

        return $this->filter(function($item) use ($key, $operator, $value) {
            $item = (object) $item;
            $actual = $item->{$key};

            $insensitive = in_array($operator, ['=i', 'like i', 'not like i']);

            if ((!is_array($actual) || !is_object($actual) || !is_numeric($actual)) && $insensitive) {
                $actual = Strings::lower(Strings::unaccent($actual));
            }

            if ((!is_array($value) || !is_object($value) || !is_numeric($actual)) && $insensitive) {
                $value  = Strings::lower(Strings::unaccent($value));
            }

            if ($insensitive) {
                $operator = str_replace(['=i', 'like i'], ['=', 'like'], $operator);
            }

            return compare($actual, $operator, $value);
        });
    }

    /**
     * @return array
     */
    public function getSchema()
    {
        $row = $this->first();

        if (!$row) {
            return [];
        }

        $fields = [];

        foreach ($row as $k => $v) {
            $type = gettype($v);

            if (strlen($v) > 255 && $type == 'string') {
                $type = 'text';
            }

            $fields[$k] = $type;
        }

        $collection = [];

        $collection['id'] = 'primary key integer';

        ksort($fields);

        foreach ($fields as $k => $v) {
            if (fnmatch('*_id', $k)) {
                $collection[$k] = 'foreign key integer';
            }
        }

        foreach ($fields as $k => $v) {
            if (fnmatch('*ed_at', $k)) {
                $collection[$k] = 'timestamp integer';
            }
        }

        foreach ($fields as $k => $v) {
            if (fnmatch('*tel*', $k)) {
                $collection[$k] = 'phone string';
            }
        }

        foreach ($fields as $k => $v) {
            if (fnmatch('*phone*', $k)) {
                $collection[$k] = 'phone string';
            }
        }

        foreach ($fields as $k => $v) {
            if (fnmatch('*mobile*', $k)) {
                $collection[$k] = 'phone string';
            }
        }

        foreach ($fields as $k => $v) {
            if (fnmatch('*cellular*', $k)) {
                $collection[$k] = 'phone string';
            }
        }

        foreach ($fields as $k => $v) {
            if (fnmatch('*fax*', $k)) {
                $collection[$k] = 'phone string';
            }
        }

        foreach ($fields as $k => $v) {
            if (fnmatch('*mail*', $k) && fnmatch('*@*', $v)) {
                $collection[$k] = 'email string';
            }
        }

        foreach ($fields as $k => $v) {
            if (fnmatch('*courriel*', $k) && fnmatch('*@*', $v)) {
                $collection[$k] = 'email string';
            }
        }

        foreach ($fields as $k => $v) {
            if (!isset($collection[$k])) {
                $collection[$k] = $v;
            }
        }

        return $collection;
    }

    public function lookfor(array $criterias)
    {
        $collection = $this;

        foreach ($criterias as $field => $value) {
            $collection = $collection->where($field, '=', $value);
        }

        return $collection;
    }

    public function q(...$args)
    {
        $collection = $this;
        $conditions = array_chunk($args, 3);

        foreach ($conditions as $condition) {
            $o = '=';

            if (count($condition) === 3) list($f, $o, $v) = $condition;
            elseif (count($condition) === 2) list($f, $v) = $condition;

            $collection = $collection->where($f, $o, $v);
        }

        return $collection;
    }

    /**
     * @param mixed ...$args
     * @return mixed
     */
    public function query(...$args)
    {
        return call_user_func_array([$this, 'q'], $args);
    }

    /**
     * @param $file
     * @throws \Exception
     */
    public function save($file)
    {
        $array = $this->native();

        File::delete($file);
        File::put($file, "<?php\n\treturn " . var_export($array, 1) . ';');
    }

    public function load($file)
    {
        if (File::exists($file)) {
            $items = include($file);
            $items = is_null($items) ? [] : $this->getArrays($items);

            $this->items = (array) $items;
        }

        return $this;
    }

    /**
     * @param $json
     * @return mixed
     * @throws \ReflectionException
     */
    public function fromJson($json)
    {
        return $this->new(json_decode($json, true));
    }

    /**
     * @param $criteria
     * @return mixed
     * @throws \ReflectionException
     */
    public function multisort($criteria)
    {
        $comparer = function ($first, $second) use ($criteria) {
            foreach ($criteria as $key => $orderType) {
                $orderType = strtolower($orderType);

                if (!isset($first[$key]) || !isset($second[$key])) {
                    return false;
                }

                if ($first[$key] < $second[$key]) {
                    return $orderType === "asc" ? -1 : 1;
                } else if ($first[$key] > $second[$key]) {
                    return $orderType === "asc" ? 1 : -1;
                }
            }

            return false;
        };

        $sorted = $this->sort($comparer);

        return $this->new($sorted->values()->toArray());
    }

    public function __call($m, $a)
    {
        if ($m === 'new') {
            return new self(current($a));
        } elseif ($m === 'array') {
            return $this->toArray();
        } elseif ($m === 'list') {
            return call_user_func_array([$this, 'lists'], $a);
        }
    }

    public function __set($key, $value)
    {
        $this->offsetSet($key, $value);
    }

    public function __isset($key)
    {
        return $this->offsetExists($key);
    }

    public function __unset($key)
    {
        return $this->forget($key);
    }

    public function __get($key)
    {
        return isAke($this->items, $key, null);
    }

    /**
     * @param array $items
     *
     * @return $this
     */
    public function replace(array $items)
    {
        foreach ($items as $key => $value) {
            $this->offsetSet($key, $value);
        }

        return $this;
    }

    public function index($index, $d = null)
    {
        return aget($this->items, $index, $d);
    }

    /**
     * @return array
     */
    public function native()
    {
        return array_values($this->toArray());
    }

    /**
     * @return Collection
     */
    public function toBase()
    {
        return is_subclass_of($this, self::class) ? new self($this) : $this;
    }

    /**
     * @param callable $callback
     *
     * @return mixed
     */
    public function pipe(callable $callback)
    {
        return $callback($this);
    }

    /**
     * @param callable $callback
     *
     * @return Collection|Tap
     *
     * @throws \ReflectionException
     */
    public function clone(callable $callback)
    {
        return $this->tap($callback);
    }

    /**
     * @param callable|null $callback
     *
     * @return Collection|Tap
     *
     * @throws \ReflectionException
     */
    public function tap(?callable $callback = null)
    {
        if (is_null($callback)) {
            return new Tap($this);
        }

        callCallable($callback, new static($this->items));

        return $this;
    }

    /**
     * @return Collection
     * @throws \ReflectionException
     */
    public function paired(): self
    {
        $res    = [];
        $args   = $this->items;
        $max    = $this->count();

        if (0 < $max && $max % 2 === 0) {
            for ($i = 0; $i < $max; $i += 2) {
                $key = $args[$i];
                $value = $args[$i + 1];

                $res[$key] = $value;
            }

            return $this->new($res);
        }
    }

    /**
     * @return string
     */
    public function toHtml(): string
    {
        return Arrays::toHtml($this->items);
    }

    /**
     * @param string $delimiter
     * @param string $enclosure
     * @param bool $encloseAll
     * @param bool $nullToMysqlNull
     *
     * @return string
     */
    public function toCsv(
        string $delimiter = ';',
        string $enclosure = '"',
        bool $encloseAll = false,
        bool $nullToMysqlNull = false
    ): string {
        return Arrays::toCsv($this->items, $delimiter, $enclosure, $encloseAll, $nullToMysqlNull);
    }

    public static function wrap($value)
    {
        return $value instanceof Collection
            ? new static($value)
            : new static(wrap($value));
    }

    public static function unwrap($value)
    {
        return $value instanceof Collection ? $value->all() : $value;
    }

    /**
     * @param $number
     *
     * @param callable|null $callback
     *
     * @return Collection
     */
    public static function times($number, ?callable $callback = null): self
    {
        if ($number < 1) {
            return new static;
        }

        if (is_null($callback)) {
            return new static(range(1, $number));
        }

        return (new static(range(1, $number)))->map($callback);
    }

    /**
     * @param null $key
     * @return float|int|mixed|null
     * @throws \ReflectionException
     */
    public function median($key = null)
    {
        $count = $this->count();

        if ($count === 0) {
            return null;
        }

        $values = (isset($key) ? $this->pluck($key) : $this)->sort()->values();

        if (!$values instanceof Collection) {
            $values = new static($values);
        }

        $middle = (int) ($count / 2);

        if ($count % 2) {
            return $values->get($middle);
        }

        return (new static([
            $values->get($middle - 1),
            $values->get($middle),
        ]))->average();
    }

    /**
     * @param null $key
     * @return null
     * @throws \ReflectionException
     */
    public function mode($key = null)
    {
        $count = $this->count();

        if ($count === 0) {
            return null;
        }

        $collection = isset($key) ? $this->pluck($key) : $this;

        $counts = new self;

        $collection->each(function ($value) use ($counts) {
            $counts[$value] = isset($counts[$value]) ? $counts[$value] + 1 : 1;
        });

        $sorted = $counts->sort();

        $highestValue = $sorted->last();

        return $sorted->filter(function ($value) use ($highestValue) {
            return $value == $highestValue;
        })->sort()->keys()->all();
    }

    /**
     * @param array ...$args
     */
    public function dd(...$args)
    {
        http_response_code(500);

        call_user_func_array([$this, 'dump'], $args);

        die(1);
    }

    /**
     * @param array ...$args
     *
     * @return Collection
     */
    public function dump(...$args): self
    {
        (new static(...$args))->push($this)->each(function ($item) {
            (new Dumper)->dump($item);
        });

        return $this;
    }

    /**
     * @param callable $callback
     *
     * @return Collection
     */
    public function eachSpread(callable $callback)
    {
        return $this->each(function ($chunk, $key) use ($callback) {
            $chunk[] = $key;

            return $callback(...$chunk);
        });
    }

    /**
     * @param $value
     * @param callable $callback
     * @param callable|null $default
     *
     * @return Collection
     */
    public function when($value, callable $callback, ?callable $default = null): self
    {
        $value = value($value);

        if ($value) {
            return $callback($this, $value);
        } elseif ($default) {
            return $default($this, $value);
        }

        return $this;
    }

    /**
     * @param $value
     * @param callable $callback
     * @param callable|null $default
     *
     * @return Collection
     */
    public function unless($value, callable $callback, ?callable $default = null)
    {
        return $this->when(!$value, $callback, $default);
    }

    /**
     * @param array ...$args
     *
     * @return mixed|null
     */
    public function firstWhere(...$args)
    {
        return $this->where(...$args)->first();
    }

    /**
     * @param array ...$args
     * @return mixed|null
     */
    public function lastWhere(...$args)
    {
        return $this->where(...$args)->last();
    }

    /**
     * @param $class
     *
     * @return Collection
     */
    public function hydrate($class)
    {
        return $this->map(function ($value, $key) use ($class) {
            return new $class($value, $key);
        });
    }

    /**
     * @param string $key
     * @param string $operator
     * @param null $value
     * @return \Closure
     */
    protected function closureWhere(string $key, string $operator, $value = null)
    {
        if (func_num_args() === 2) {
            $value      = $operator;
            $operator   = '=';
        }

        return function ($item) use ($key, $operator, $value) {
            $operator = Inflector::lower($operator);
            $actual = data_get($item, $key);

            $strings = array_filter([$actual, $value], function ($value) {
                return is_string($value) || (is_object($value) && method_exists($value, '__toString'));
            });

            if (count($strings) < 2 && count(array_filter([$actual, $value], 'is_object')) === 1) {
                return in_array($operator, ['!=', '<>', '!==']);
            }

            return compare($actual, $operator, $value);
        };
    }

    public function every($key, $operator = null, $value = null)
    {
        if (func_num_args() === 1) {
            $callback = $this->makeClosure($key);

            foreach ($this->items as $k => $v) {
                if (!$callback($v, $k)) {
                    return false;
                }
            }

            return true;
        }

        if (func_num_args() === 2) {
            $value = $operator;

            $operator = '=';
        }

        return $this->every($this->closureWhere($key, $operator, $value));
    }

    /**
     * @param string $key
     * @param null $operator
     * @param null $value
     *
     * @return Collection
     */
    public function partition($key, $operator = null, $value = null)
    {
        $partitions = [new static, new static];

        $callback = func_num_args() === 1
            ? $this->makeClosure($key)
            : $this->closureWhere(...func_get_args());

        foreach ($this->items as $k => $item) {
            $partitions[(int) !$callback($item, $k)][$k] = $item;
        }

        return new static($partitions);
    }

    /**
     * @param callable $callback
     *
     * @return mixed
     */
    public function proxy(callable $callback)
    {
        return $callback($this);
    }

    /**
     * @param $source
     *
     * @return Collection
     */
    public function concat($source)
    {
        $result = clone $this;

        foreach ($source as $item) {
            $result->push($item);
        }

        return $result;
    }
}
