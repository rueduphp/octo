<?php
    namespace Octo;

    use Countable;
    use ArrayAccess;
    use ArrayIterator;
    use CachingIterator;
    use JsonSerializable;
    use IteratorAggregate;

    class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
    {
        use Macroable;

        protected $items = [];

        public function __construct($items = [])
        {
            $items = is_null($items) ? [] : $this->getArrayItems($items);

            $this->items = (array) $items;
        }

        public function create()
        {
            $affected = 0;

            if (!empty($this->items)) {
                $first = current($this->items);

                if ($first instanceof Object) {
                    foreach ($this->items as $item) {
                        if ($item->hasModel()) {
                            $row = $item->save();
                            $affected++;
                        }
                    }
                }
            }

            return $affected;
        }

        public static function make($items = null)
        {
            return new static($items);
        }

        public function all()
        {
            return $this->items;
        }

        public function collapse()
        {
            $results = [];

            foreach ($this->items as $values) {
                if ($values instanceof self) $values = $values->all();

                $results = array_merge($results, $values);
            }

            return $this->new($results);
        }

        public function contains($key, $value = null)
        {
            if (func_num_args() == 2) {
                return $this->contains(function($k, $item) use ($key, $value) {
                    return dget($item, $key) == $value;
                });
            }

            if (is_callable($key)) {
                return !is_null($this->first($key));
            }

            return in_array($key, $this->items);
        }

        public function diff($items)
        {
            return $this->new(array_diff($this->items, $this->getArrayItems($items)));
        }

        public function each(callable $callback)
        {
            array_map($callback, $this->items);

            return $this;
        }

        public function fetch($key)
        {
            return $this->new(Arrays::fetchOne($this->items, $key));
        }

        public function filter(callable $callback)
        {
            return $this->new(array_filter($this->items, $callback));
        }

        public function whereLoose($key, $value)
        {
            return $this->where($key, $value, false);
        }

        public function first(callable $callback = null, $default = null)
        {
            if (is_null($callback)) {
                return !empty($this->items) ? reset($this->items) : $default;
            }

            return Arrays::firstOne($this->items, $callback, $default);
        }

        public function flatten()
        {
            return $this->new(Arrays::flatten($this->items));
        }

        public function flip()
        {
            return $this->new(array_flip($this->items));
        }

        public function forget($key)
        {
            $this->offsetUnset($key);
        }

        public function get($key, $default = null)
        {
            if ($this->offsetExists($key)) {
                return $this->items[$key];
            }

            return File::value($default);
        }

        public function groupBy($groupBy)
        {
            if ( ! $this->useAsCallable($groupBy)) {
                return $this->groupBy($this->valueResolver($groupBy));
            }

            $results = [];

            foreach ($this->items as $key => $value) {
                $groupKey = $groupBy($value, $key);

                if ( ! array_key_exists($groupKey, $results)) {
                    $results[$groupKey] = $this->new([]);
                }

                $results[$groupKey]->push($value);
            }

            return $this->new($results);
        }

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

        public function keyBy($keyBy)
        {
            if ( ! $this->useAsCallable($keyBy)) {
                return $this->keyBy($this->valueResolver($keyBy));
            }

            $results = [];

            foreach ($this->items as $item) {
                $results[$keyBy($item)] = $item;
            }

            return $this->new($results);
        }

        public function between($field = 'id', $min = 0, $max = 0)
        {
            if (!$this->useAsCallable($field)) {
                return $this->between($this->valueResolver($field), $min, $max);
            }

            $results = [];

            foreach ($this->items as $key => $value) {
                $val = $field($value);

                if ($val >= $min && $val <= $max) {
                    $results[] = $value;
                }
            }

            return $this->new($results);
        }

        public function has($key)
        {
            return $this->offsetExists($key);
        }

        public function implode($value, $glue = null)
        {
            $first = $this->first();

            if (is_array($first) || is_object($first)) {
                return implode($glue, $this->lists($value));
            }

            return implode($value, $this->items);
        }

        public function intersect($items)
        {
            return $this->new(array_intersect($this->items, $this->getArrayItems($items)));
        }

        public function isEmpty()
        {
            return empty($this->items);
        }

        protected function useAsCallable($value)
        {
            return !is_string($value) && is_callable($value);
        }

        public function keys()
        {
            return $this->new(array_keys($this->items));
        }

        public function last($default = null)
        {
            return !empty($this->items) ? end($this->items) : $default;
        }

        public function lists($value, $key = null)
        {
            return Arrays::pluck($this->items, $value, $key);
        }

        public function map(callable $callback)
        {
            return $this->new(array_map($callback, $this->items, array_keys($this->items)));
        }

        public function merge($items)
        {
            return $this->new(array_merge($this->items, $this->getArrayItems($items)));
        }

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

        public function pull($key, $default = null)
        {
            return Arrays::pull($this->items, $key, $default);
        }

        public function put($key, $value)
        {
            $this->offsetSet($key, $value);
        }

        public function random($amount = 1)
        {
            if ($this->isEmpty()) {
                return;
            }

            $keys = array_rand($this->items, $amount);

            return is_array($keys) ? array_intersect_key($this->items, array_flip($keys)) : $this->items[$keys];
        }

        public function reduce(callable $callback, $initial = null)
        {
            return array_reduce($this->items, $callback, $initial);
        }

        public function reject($callback)
        {
            if ($this->useAsCallable($callback)) {
                return $this->filter(function($item) use ($callback) {
                    return !$callback($item);
                });
            }

            return $this->filter(function($item) use ($callback) {
                return $item != $callback;
            });
        }

        public function reverse()
        {
            return $this->new(array_reverse($this->items, true));
        }

        public function search($value, $strict = false)
        {
            if (! $this->useAsCallable($value)) {
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

        public function shuffle()
        {
            $items = $this->items;

            shuffle($items);

            return $this->new($items);
        }

        public function sortByRand()
        {
            $items = $this->items;

            shuffle($items);

            return $this->new($items);
        }

        public function slice($offset, $length = null)
        {
            return $this->new(array_slice($this->items, $offset, $length, true));
        }

        public function chunk($size)
        {
            $chunks = [];

            foreach (array_chunk($this->items, $size, true) as $chunk) {
                $chunks[] = $this->new($chunk);
            }

            return $this->new($chunks);
        }

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

        public function sortBy($callback, $options = SORT_REGULAR, $descending = false)
        {
            $results = [];

            if (!$this->useAsCallable($callback)) {
                $callback = $this->valueResolver($callback);
            }

            foreach ($this->items as $key => $value) {
                $results[$key] = $callback($value);
            }

            $descending ? arsort($results, $options) : asort($results, $options);

            foreach (array_keys($results) as $key) {
                $results[$key] = $this->items[$key];
            }

            $this->items = $results;

            return $this;
        }

        public function sortByDesc($callback, $options = SORT_REGULAR)
        {
            return $this->sortBy($callback, $options, true);
        }

        public function splice($offset, $length = null, $replacement = [])
        {
            if (func_num_args() == 1) {
                return $this->new(array_splice($this->items, $offset));
            }

            return $this->new(array_splice($this->items, $offset, $length, $replacement));
        }

        public function sum($callback = null)
        {
            if (is_null($callback)) {
                return array_sum($this->items);
            }

            if (!$this->useAsCallable($callback)) {
                $callback = $this->valueResolver($callback);
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

        public function average($field = null)
        {
            return $this->avg($field);
        }

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

        public function unique($key = null)
        {
            if (is_null($key)) {
                return $this->new(array_unique($this->items, SORT_REGULAR));
            }

            $key = $this->valueResolver($key);

            $exists = [];

            return $this->reject(function ($item) use ($key, &$exists) {
                if (in_array($id = $key($item), $exists)) {
                    return true;
                }

                $exists[] = $id;
            });
        }

        public function values()
        {
            return $this->new(array_values($this->items));
        }

        public function avalues()
        {
            return array_values($this->items);
        }

        protected function valueResolver($value)
        {
            return function($item) use ($value) {
                return Arrays::data_get($item, $value);
            };
        }

        public function zip($items)
        {
            $arrayableItems = array_map(function ($items) {
                return $this->getArrayItems($items);
            }, func_get_args());

            $params = array_merge([function () {
                return $this->new(func_get_args());
            }, $this->items], $arrayableItems);

            return $this->new(call_user_func_array('array_map', $params));
        }

        public function toArray()
        {
            return array_map(function($value) {
                return $value instanceof Collection ? $value->toArray() : $value;

            }, $this->items);
        }

        public function jsonSerialize()
        {
            return $this->toArray();
        }

        public function toJson($options = 0)
        {
            return json_encode($this->toArray(), $options);
        }

        public function getIterator()
        {
            return new ArrayIterator($this->items);
        }

        public function getCachingIterator($flags = CachingIterator::CALL_TOSTRING)
        {
            return new CachingIterator($this->getIterator(), $flags);
        }

        public function count()
        {
            return count($this->items);
        }

        public function offsetExists($key)
        {
            return array_key_exists($key, $this->items);
        }

        public function offsetGet($key)
        {
            return $this->items[$key];
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

        public function __toString()
        {
            return $this->toJson();
        }

        public function nth($key, $d = null)
        {
            return (isset($this->items[$key])) ? $this->items[$key] : $d;
        }

        protected function getArrayItems($items)
        {
            if ($items instanceof Collection) {
                $items = $items->all();
            } elseif (is_object($items)) {
                $methods = get_class_methods($items);

                if (in_array('toArray', $methods)) {
                    $items = $items->toArray();
                }
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

        public function where($key, $operator = null, $value = null)
        {
            if (func_num_args() == 1) {
                if (is_array($key)) {
                    list($key, $operator, $value) = $key;
                    $operator = strtolower($operator);
                }
            }

            if (func_num_args() == 2) {
                list($value, $operator) = [$operator, '='];
            }

            return $this->filter(function($item) use ($key, $operator, $value) {
                $item = (object) $item;
                $actual = $item->{$key};

                $insensitive = in_array($operator, ['=i', 'like i', 'not like i']);

                if ((!is_array($actual) || !is_object($actual)) && $insensitive) {
                    $actual = Strings::lower(Strings::unaccent($actual));
                }

                if ((!is_array($value) || !is_object($value)) && $insensitive) {
                    $value  = Strings::lower(Strings::unaccent($value));
                }

                if ($insensitive) {
                    $operator = str_replace(['=i', 'like i'], ['=', 'like'], $operator);
                }

                switch ($operator) {
                    case '<>':
                    case '!=':
                        return sha1(serialize($actual)) != sha1(serialize($value));
                    case '>':
                        return $actual > $value;
                    case '<':
                        return $actual < $value;
                    case '>=':
                        return $actual >= $value;
                    case '<=':
                        return $actual <= $value;
                    case 'between':
                        return $actual >= $value[0] && $actual <= $value[1];
                    case 'not between':
                        return $actual < $value[0] || $actual > $value[1];
                    case 'in':
                        return in_array($actual, $value);
                    case 'not in':
                        return !in_array($actual, $value);
                    case 'like':
                        $value  = str_replace("'", '', $value);
                        $value  = str_replace('%', '*', $value);

                        return fnmatch($value, $actual);
                    case 'not like':
                        $value  = str_replace("'", '', $value);
                        $value  = str_replace('%', '*', $value);

                        $check  = fnmatch($value, $actual);

                        return !$check;
                    case 'is':
                        return is_null($actual);
                    case 'is not':
                        return !is_null($actual);
                    case '=':
                    default:
                        return sha1(serialize($actual)) == sha1(serialize($value));
                }
            });
        }

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

        public function q()
        {
            $collection = $this;
            $conditions = array_chunk(func_get_args(), 3);

            foreach ($conditions as $condition) {
                list($f, $o, $v) = $condition;
                $collection = $collection->where($f, $o, $v);
            }

            return $collection;
        }

        public function query()
        {
            return call_user_func_array([$this, 'q'], func_get_args());
        }

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
                $items = is_null($items) ? [] : $this->getArrayItems($items);

                $this->items = (array) $items;
            }

            return $this;
        }

        public function fromJson($json)
        {
            return $this->new(json_decode($json, true));
        }

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
            if ($m == 'new') {
                return new self(current($a));
            } elseif ($m == 'array') {
                return $this->toArray();
            } elseif ($m == 'list') {
                return call_user_func_array([$this, 'lists'], $a);
            }
        }

        public function __set($key, $value)
        {
            $this->items[$key] = $value;

            return $this;
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

        public function index($index, $d = null)
        {
            return aget($this->items, $index, $d);
        }

        public function native()
        {
            return array_values($this->toArray());
        }

        public function toBase()
        {
            return is_subclass_of($this, self::class) ? new self($this) : $this;
        }
    }
