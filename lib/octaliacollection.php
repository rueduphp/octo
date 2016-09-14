<?php

    namespace Octo;

    use LogicException;

    class OctaliaCollection extends Collection
    {
        /**
         * Find a model in the collection by key.
         *
         * @param  mixed  $key
         * @param  mixed  $default
         * @return \Octo\Object
         */
        public function find($key, $default = null)
        {
            if ($key instanceof Object) {
                $key = $key->getId();
            }

            return Arrays::first($this->items, function ($model) use ($key) {
                return $model->getId() == $key;
            }, $default);
        }

        /**
         * Add an item to the collection.
         *
         * @param  mixed  $item
         * @return $this
         */
        public function add($item)
        {
            $this->items[] = $item;

            return $this;
        }

        /**
         * Determine if a key exists in the collection.
         *
         * @param  mixed  $key
         * @param  mixed  $value
         * @return bool
         */
        public function contains($key, $value = null)
        {
            if (func_num_args() == 2) {
                return parent::contains($key, $value);
            }

            if ($this->useAsCallable($key)) {
                return parent::contains($key);
            }

            $key = $key instanceof Object ? $key->getId() : $key;

            return parent::contains(function ($model) use ($key) {
                return $model->getId() == $key;
            });
        }

        /**
         * Get the array of primary keys.
         *
         * @return array
         */
        public function modelKeys()
        {
            return array_map(function ($model) {
                return $model->getId();
            }, $this->items);
        }

        /**
         * Merge the collection with the given items.
         *
         * @param  \ArrayAccess|array  $items
         * @return static
         */
        public function merge($items)
        {
            $dictionary = $this->getDictionary();

            foreach ($items as $item) {
                $dictionary[$item->getId()] = $item;
            }

            return new static(array_values($dictionary));
        }

        /**
         * Run a map over each of the items.
         *
         * @param  callable  $callback
         * @return static
         */
        public function map(callable $callback)
        {
            $result = parent::map($callback);

            return $result->contains(function ($item) {
                return ! $item instanceof Object;
            }) ? $result->toBase() : $result;
        }

        /**
         * Diff the collection with the given items.
         *
         * @param  \ArrayAccess|array  $items
         * @return static
         */
        public function diff($items)
        {
            $diff = new static;

            $dictionary = $this->getDictionary($items);

            foreach ($this->items as $item) {
                if (!isset($dictionary[$item->getId()])) {
                    $diff->add($item);
                }
            }

            return $diff;
        }

        /**
         * Intersect the collection with the given items.
         *
         * @param  \ArrayAccess|array  $items
         * @return static
         */
        public function intersect($items)
        {
            $intersect = new static;

            $dictionary = $this->getDictionary($items);

            foreach ($this->items as $item) {
                if (isset($dictionary[$item->getId()])) {
                    $intersect->add($item);
                }
            }

            return $intersect;
        }

        /**
         * Return only unique items from the collection.
         *
         * @param  string|callable|null  $key
         * @param  bool  $strict
         * @return static
         */
        public function unique($key = null, $strict = false)
        {
            if (! is_null($key)) {
                return parent::unique($key, $strict);
            }

            return new static(array_values($this->getDictionary()));
        }

        /**
         * Returns only the models from the collection with the specified keys.
         *
         * @param  mixed  $keys
         * @return static
         */
        public function only($keys)
        {
            $dictionary = Arrays::only($this->getDictionary(), $keys);

            return new static(array_values($dictionary));
        }

        /**
         * Returns all models in the collection except the models with specified keys.
         *
         * @param  mixed  $keys
         * @return static
         */
        public function except($keys)
        {
            $dictionary = Arrays::except($this->getDictionary(), $keys);

            return new static(array_values($dictionary));
        }

        public function makeHidden($attributes)
        {
            return true;
        }

        public function makeVisible($attributes)
        {
            return true;
        }

        /**
         * Get a dictionary keyed by primary keys.
         *
         * @param  \ArrayAccess|array|null  $items
         * @return array
         */
        public function getDictionary($items = null)
        {
            $items = is_null($items) ? $this->items : $items;

            $dictionary = [];

            foreach ($items as $value) {
                $dictionary[$value->getId()] = $value;
            }

            return $dictionary;
        }

        /**
         * The following methods are intercepted to always return base collections.
         */

        /**
         * Get an array with the values of a given key.
         *
         * @param  string  $value
         * @param  string|null  $key
         * @return \Octo\Collection
         */
        public function pluck($value, $key = null)
        {
            return $this->toBase()->pluck($value, $key);
        }

        /**
         * Get the keys of the collection items.
         *
         * @return \Octo\Collection
         */
        public function keys()
        {
            return $this->toBase()->keys();
        }

        /**
         * Zip the collection together with one or more arrays.
         *
         * @param  mixed ...$items
         * @return \Octo\Collection
         */
        public function zip($items)
        {
            return call_user_func_array([$this->toBase(), 'zip'], func_get_args());
        }

        /**
         * Collapse the collection of items into a single array.
         *
         * @return \Octo\Collection
         */
        public function collapse()
        {
            return $this->toBase()->collapse();
        }

        /**
         * Get a flattened array of the items in the collection.
         *
         * @param  int  $depth
         * @return \Octo\Collection
         */
        public function flatten($depth = INF)
        {
            return $this->toBase()->flatten($depth);
        }

        /**
         * Flip the items in the collection.
         *
         * @return \Octo\Collection
         */
        public function flip()
        {
            return $this->toBase()->flip();
        }

        /**
         * Get the type of the entities being queued.
         *
         * @return string|null
         */
        public function getQueueableClass()
        {
            if ($this->count() === 0) {
                return;
            }

            $class = get_class($this->first());

            $this->each(function ($model) use ($class) {
                if (get_class($model) !== $class) {
                    throw new LogicException('Queueing collections with multiple model types is not supported.');
                }
            });

            return $class;
        }

        /**
         * Get the identifiers for all of the entities.
         *
         * @return array
         */
        public function getQueueableIds()
        {
            return $this->modelKeys();
        }
    }
