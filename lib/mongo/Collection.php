<?php
    namespace Octo\Mongo;

    use IteratorAggregate;
    use ArrayAccess;
    use Countable;
    use ArrayIterator;
    use Closure;

    use Octo\Container;
    use Octo\Arrays;
    use Octo\Exception;
    use Octo\Inflector;

    class Collection implements IteratorAggregate, ArrayAccess, Countable
    {
        private $_items     = array();
        private $is_where   = false;
        private $_key;

        /**
         * Make a collection from a array of Model
         *
         * @param array $models models to add to the collection
         */
        public function __construct($models = array(), $key = null)
        {
            $items = array();
            $i = 0;

            if (count($models)) {
                foreach ($models as $model) {
                    $id = $i++;

                    if (is_object($model)) {
                        if ($model->exists()) {
                            $id = (int) $model->id;
                        } else {
                            $model->setTempId($id);
                        }
                    }

                    $items[$id] = $model;
                }
            }

            $this->_items   = $items;
            $this->_key     = 'collection::' . $key;
        }

        /**
         * Add a model item or model array or ModelSet to this set
         *
         * @param mixed $items model item or arry or ModelSet to add
         *
         * @return $this
         */
        public function add($items)
        {
            if ($items && is_object($items)) {
                $id = (int) $items->id;
                $this->_items[$id] = $items;
            } elseif (Arrays::is($items)) {
                foreach ($items as $obj) {
                    if (is_object($obj)) {
                        $this->add($obj);
                    }
                }
            } elseif ($items instanceof self) {
                $this->add($items->toArray());
            }

            return $this;
        }

        /**
         * Get item by numeric index
         *
         * @param int $index model to get
         *
         * @return Model
         */
        public function get($index = 0)
        {
            if (is_integer($index)) {
                if ($index + 1 > $this->count()) {
                    return null;
                } else {
                    return Arrays::first(array_slice($this->_items, $index, 1));
                }
            } else {
                if ($this->has($index)) {
                    return $this->_items[$index];
                }
            }

            return null;
        }

        /**
         * Remove an item from the collection by key.
         *
         * @param  mixed  $key
         * @return void
         */
        public function forget($key)
        {
            unset($this->_items[$key]);
        }

        /**
         * Remove a record from the collection
         *
         * @param int|Model $param model to remove
         *
         * @return boolean
         */
        public function remove($param)
        {
            if (is_object($param)) {
                $param = $param->id;
            }

            $item = $this->get($param);

            if ($item) {
                $id = (int) $item->id;

                if ($this->_items[$id]) {
                    unset($this->_items[$id]);
                }
            }

            return $this;
        }

        /**
         * Slice the underlying collection array.
         *
         * @param int  $offset       offset to slice
         * @param int  $length       length
         * @param bool $preserveKeys preserve keys
         *
         * @return Collection
         */
        public function slice($offset, $length = null, $preserveKeys = false)
        {
            return new self(array_slice($this->_items, $offset, $length, $preserveKeys));
        }

        /**
         * Take the first or last {$limit} items.
         *
         * @param int $limit limit
         *
         * @return Collection
         */
        public function take($limit = null)
        {
            if ($limit < 0) return $this->slice($limit, abs($limit));

            return $this->slice(0, $limit);
        }

        /**
         * Determine if the collection is empty or not.
         *
         * @return bool
         */
        public function isEmpty()
        {
            return empty($this->_items);
        }

        /**
         * Determine if a record exists in the collection
         *
         * @param int|object $param param
         *
         * @return boolean
         */
        public function has($param)
        {
            if (is_object($param)) {
                $id = (int) $param->id;
            } elseif (is_integer($param)) {
                $id = $param;
            }

            if (isset($id) && isset($this->_items[$id])) {
                return true;
            }

            return false;
        }

        /**
         * Determine if an item exists in the collection.
         *
         * @param  mixed  $value
         * @return bool
         */
        public function contains($value)
        {
            if ($value instanceof \Closure) {
                return ! is_null($this->first($value));
            }

            return Arrays::in($value, $this->_items);
        }

        /**
         * Run a map over the collection using the given Closure
         *
         * @param Closure $callback callback
         *
         * @return Collection
         */
        public function map(Closure $callback)
        {
            $this->_items = array_map($callback, $this->_items);

            return $this;
        }

        /**
         * Filter the collection using the given Closure and return a new collection
         *
         * @param Closure $callback callback
         *
         * @return Collection
         */
        public function filter(Closure $callback)
        {
            return new self(array_filter($this->_items, $callback));
        }

        /**
         * Sort the collection using the given Closure
         *
         * @param Closure $callback callback
         * @param boolean $asc      asc
         *
         * @return Collection
         */
        public function sortBy(Closure $callback, $args = array(), $asc = false)
        {
            $results = array();

            foreach ($this->_items as $key => $value) {
                array_push($args, $value);
                $results[$key] = call_user_func_array($callback, $args);
            }

            if (true === $asc) {
                asort($results);
            } else {
                arsort($results);
            }

            foreach (array_keys($results) as $key) {
                $results[$key] = $this->_items[$key];
            }

            $this->_items = $results;

            return $this;
        }

        public function orderBy($fieldOrder, $orderDirection = 'ASC')
        {
            $sortFunc = function($key, $direction) {
                return function ($a, $b) use ($key, $direction) {
                    if ('ASC' == $direction) {
                        return $a[$key] > $b[$key];
                    } else {
                        return $a[$key] < $b[$key];
                    }
                };
            };

            if (Arrays::is($fieldOrder) && !Arrays::is($orderDirection)) {
                $t = array();

                foreach ($fieldOrder as $tmpField) {
                    array_push($t, $orderDirection);
                }

                $orderDirection = $t;
            }

            if (!Arrays::is($fieldOrder) && Arrays::is($orderDirection)) {
                $orderDirection = Arrays::first($orderDirection);
            }

            if (Arrays::is($fieldOrder) && Arrays::is($orderDirection)) {
                for ($i = 0 ; $i < count($fieldOrder) ; $i++) {
                    usort($this->_items, $sortFunc($fieldOrder[$i], $orderDirection[$i]));
                }
            } else {
                usort($this->_items, $sortFunc($fieldOrder, $orderDirection));
            }

            if ($this->_key != 'collection::') {
                redis()->hset($this->_key, sha1(serialize(func_get_args())), serialize($this->_items));
            }

            return $this;
        }

        /**
         * Group an associative array by a field or Closure value.
         *
         * @param  callable|string  $groupBy
         * @return Collection
         */
        public function groupBy($groupBy)
        {
            $results = array();

            foreach ($this->_items as $key => $value) {
                $key = is_callable($groupBy) ? $groupBy($value, $key) : dataGet($value, $groupBy);
                $results[$key][] = $value;
            }

            return new self($results);
        }

        /**
         * Key an associative array by a field.
         *
         * @param  string  $keyBy
         * @return Collection
         */
        public function keyBy($keyBy)
        {
            $results = array();

            foreach ($this->_items as $item) {
                $key = dataGet($item, $keyBy);
                $results[$key] = $item;
            }

            return new self($results);
        }

        public function keep($keys, $returnCollection = true)
        {
            /* polymorphism */
            $keys = !Arrays::is($keys)
            ? strstr($keys, ',')
                ? explode(',', repl(' ', '', $keys))
                : [$keys]
            : $keys;

            $results = [];

            if (count($this->_items)) {
                foreach ($this->_items as $item) {
                    $value = [];

                    foreach ($keys as $key) {
                        array_push($value, isAke($item, $key, null));
                    }

                    array_push($results, implode(' ', $value));
                }
            }

            return true === $returnCollection ? new self($results) : $results;
        }

        /**
         * Reverse items order.
         *
         * @return Collection
         */
        public function reverse()
        {
            $this->_items = array_reverse($this->_items);

            return $this;
        }

        /**
         * Make a collection from an array of Model
         *
         * @param array $items items
         *
         * @return Collection
         */
        public static function make($items)
        {
            if (is_null($items)) return new static;

            if ($items instanceof Collection) return $items;

            return new static(is_array($items) ? $items : array($items));
        }

        /**
         * Collapse the collection items into a single array.
         *
         * @return Collection
         */
        public function collapse()
        {
            $results = array();

            foreach ($this->_items as $values) {
                $results = array_merge($results, $values);
            }

            return new static($results);
        }


        /**
         * Get all of the items in the collection.
         *
         * @return array
         */
        public function all()
        {
            return $this->_items;
        }

        /**
         * First item
         *
         * @return Model
         */
        public function first($callback = null, $default = null)
        {
            if (is_null($callback)) {
                return count($this->_items) > 0 ? Arrays::first($this->_items) : $default;
            } else {
                foreach ($this->_items as $key => $value) {
                    if (call_user_func($callback, $key, $value)) {
                        return $value;
                    }
                }

                return value($default);
            }
        }

        /**
         * Last item
         *
         * @return Model
         */
        public function last()
        {
            return count($this->_items) > 0 ? Arrays::last($this->_items) : null;
        }

        public function items($array = false)
        {
            return true === $array ? $this->toArray(false, $array) : $this->_items;
        }

        public function rows($array = false)
        {
            return $this->items($array);
        }

        /**
         * Execute a callback over each item.
         *
         * @param Closure $callback callback
         *
         * @return Collection
         */
        public function each(Closure $callback)
        {
            array_map($callback, $this->_items);

            return $this;
        }

        /**
         * extends each Container item of this collection with a Closure.
         *
         * @param  string  $name
         * @param Closure $callback callback
         * @return Collection
         */

        public function extend($name, Closure $callback)
        {
            if (!empty($this->_items)) {
                $collection = [];

                foreach ($this->_items as $item) {
                    if ($item instanceof Container) {
                        $item->fn($name, $callback);
                    }

                    array_push($collection, $item);
                }

                return new self($collection);
            }

            return $this;
        }

        /**
         * Fetch a nested element of the collection.
         *
         * @param  string  $key
         * @return Collection
         */
        public function fetch($key)
        {
            return new self(Arrays::fetch($this->_items, $key));
        }

        /**
         * Count items
         *
         * @return int
         */
        public function count()
        {
            return count($this->_items);
        }

        /**
         * Export all items to a Array
         *
         * @param boolean $is_numeric_index is numeric index
         * @param boolean $itemToArray      item to array
         *
         * @return array
         */
        public function toArray($isNumericIndex = true, $itemToArray = false)
        {
            $array = [];

            foreach ($this->_items as $item) {
                if (false === $isNumericIndex) {
                    if (true === $itemToArray) {
                        if (is_object($item)) {
                            $item = $item->assoc();
                        }
                    }
                } else {
                    if (true === $itemToArray) {
                        if (is_object($item)) {
                            $item = $item->assoc();
                        }
                    }
                }

                $array[] = $item;
            }

            return $array;
        }


        /**
         * Export all items to a json string
         *
         * @param boolean $is_numeric_index is numeric index
         * @param boolean $itemToArray      item to array
         *
         * @return string
         */
        public function toJson($render = false)
        {
            $json = json_encode($this->toArray(true, true));

            if (false === $render) {
                return $json;
            } else {
                header('content-type: application/json; charset=utf-8');
                die($json);
            }
        }

        /**
         *
         * @return array
         */
        public function toEmbedsArray()
        {
            $array = [];

            foreach ($this->_items as $item) {
                $item = $item->assoc();
                $array[] = $item;
            }

            return $array;
        }

        /**
         * get iterator
         *
         * @return ArrayIterator
         */
        public function getIterator()
        {
            return new ArrayIterator($this->_items);
        }

        /**
         * Offset exists
         *
         * @param int|string $key index
         *
         * @return boolean
         */
        public function offsetExists($key)
        {
            if (is_integer($key) && $key + 1 <= $this->count()) {
                return true;
            }

            return $this->has($key);
        }

        /**
         * Offset get
         *
         * @param int|string $key index
         *
         * @return boolean
         */
        public function offsetGet($key)
        {
            return $this->get($key);
        }

        /**
         * Offset set
         *
         * @param mixed $offset offset
         * @param mixed $value  value
         *
         * @throws Exception
         *
         * @return null
         */
        public function offsetSet($offset, $value)
        {
            throw new Exception('cannot change the set by using []');
        }

        /**
         * Offset unset
         *
         * @param int $index index
         *
         * @return bool
         */
        public function offsetUnset($index)
        {
            $this->remove($index);
        }

        /**
         * Save items
         *
         * @return Collection
         */
        public function save()
        {
            if (count($this->_items)) {
                foreach($this->_items as $item) {
                    if(true === $item->exists()) {
                        $item->save();
                    }
                }
            }

            return $this;
        }

        /**
         * Delete items
         *
         * @return Collection
         */
        public function delete()
        {
            if (count($this->_items)) {
                foreach($this->_items as $key => $item) {
                    if(true === $item->exists()) {
                        $deleted = $item->delete();
                        unset($this->_items[$key]);
                    }
                }
            }

            return $this;
        }

        private function indexes()
        {
            $rows = $this->rows();
            $ids = [];

            foreach ($rows as $row) {
                if (is_object($row)) {
                    array_push($ids, $row->id);
                }
            }

            return $ids;
        }

        public function __call($method, $args)
        {
            if (count($this->_items)) {
                $first = $this->first();

                $db         = $first->db();
                $methods    = get_class_methods($db);

                if (Arrays::in($method, $methods)) {
                    $instance = $db->where(['id', 'IN', implode(',', $this->indexes())]);

                    return call_user_func_array([$instance, $method], $args);
                }
            }
        }

        /**
         * Concatenate values of a given key as a string.
         *
         * @param  string  $value
         * @param  string  $glue
         * @return string
         */
        public function implode($value, $glue = null)
        {
            if (is_null($glue)) return implode($this->lists($value));

            return implode($glue, $this->lists($value));
        }

        /**
         * Get an array with the values of a given key.
         *
         * @param  string  $value
         * @param  string  $key
         * @return array
         */
        public function lists($value, $key = null)
        {
            return arrayPluck($this->_items, $value, $key);
        }

        /**
         * Convert the collection to its string representation.
         *
         * @return string
         */
        public function __toString()
        {
            return $this->toJson();
        }

        /**
         * Results array of items from Collection or ArrayableInterface.
         *
         * @param  $items
         * @return array
         */
        protected function getArrayableItems($items)
        {
            if ($items instanceof Collection) {
                $items = $items->rows(true);
            }

            return $items;
        }

        public function getDictionary($items = null)
        {
            $items = is_null($items) ? $this->_items : $items;

            $dictionary = [];

            foreach ($items as $value) {
                $dictionary[$value->id] = $value;
            }

            return $dictionary;
        }

        public function unique()
        {
            return new self(array_values($this->getDictionary()));
        }

        public function only($keys)
        {
            $dictionary = array_only($this->getDictionary(), $keys);

            return new self(array_values($dictionary));
        }

        public function except($keys)
        {
            $dictionary = array_except($this->getDictionary(), $keys);

            return new self(array_values($dictionary));
        }

        public function intersect($items)
        {
            $intersect = new self;

            $dictionary = $this->getDictionary($items);

            foreach ($this->items as $item) {
                if (isset($dictionary[$item->id])) {
                    $intersect->add($item);
                }
            }

            return $intersect;
        }

        public function diff($items)
        {
            $diff = new self;

            $dictionary = $this->getDictionary($items);

            foreach ($this->_items as $item) {
                if (!isset($dictionary[$item->id])) {
                    $diff->add($item);
                }
            }

            return $diff;
        }

        public function merge($items)
        {
            $dictionary = $this->getDictionary();

            foreach ($items as $item) {
                $dictionary[$item->id] = $item;
            }

            return new self(array_values($dictionary));
        }

        public function modelKeys()
        {
            return array_map(function($m) { return $m->id; }, $this->_items);
        }

        public function reduce($callback, $initial = null)
        {
            return array_reduce($this->_items, $callback, $initial);
        }

        public function find($key, $default = null)
        {
            if (is_object($key)) {
                $key = $key->id;
            }

            return array_first($this->_items, function($itemKey, $model) use ($key) {
                return $model->id == $key;

            }, $default);
        }

        public function max($key)
        {
            return $this->reduce(function($result, $item) use ($key)
            {
                return (is_null($result) || $item->{$key} > $result) ? $item->{$key} : $result;
            });
        }

        public function min($key)
        {
            return $this->reduce(function($result, $item) use ($key)
            {
                return (is_null($result) || $item->{$key} < $result) ? $item->{$key} : $result;
            });
        }
    }
