<?php
    namespace Octo;

    use ArrayAccess as AA;

    class Fluent implements AA
    {
        use Macroable;
        use Notifiable;

        /**
         * All of the attributes set on the container.
         *
         * @var array
         */
        protected $attributes = [];

        /**
         * Create a new fluent container instance.
         *
         * @param  array  $attributes
         * @return void
         */
        public function __construct($attributes = [])
        {
            $attributes = arrayable($attributes) ? $attributes->toArray() : $attributes;

            foreach ($attributes as $key => $value) {
                $this->attributes[$key] = $value;
            }
        }

        /**
         * Get an attribute from the container.
         *
         * @param  string  $key
         * @param  mixed   $default
         * @return mixed
         */
        public function get($key, $default = null)
        {
            if (array_key_exists($key, $this->attributes)) {
                return $this->attributes[$key];
            }

            return value($default);
        }

        /**
         * Get the attributes from the container.
         *
         * @return array
         */
        public function getAttributes()
        {
            return $this->attributes;
        }

        /**
         * Convert instance to an array.
         *
         * @return array
         */
        public function toArray()
        {
            return $this->attributes;
        }

        /**
         * Convert the Fluent instance to JSON.
         *
         * @param  int  $options
         * @return string
         */
        public function toJson($options = 0)
        {
            return json_encode($this->toArray(), $options);
        }

        /**
         * Determine if the given offset exists.
         *
         * @param  string  $offset
         * @return bool
         */
        public function offsetExists($offset)
        {
            return isset($this->{$offset});
        }

        /**
         * Get the value for a given offset.
         *
         * @param  string  $offset
         * @return mixed
         */
        public function offsetGet($offset)
        {
            return $this->{$offset};
        }

        /**
         * Set the value at the given offset.
         *
         * @param  string  $offset
         * @param  mixed   $value
         * @return void
         */
        public function offsetSet($offset, $value)
        {
            $this->{$offset} = $value;
        }

        /**
         * Unset the value at the given offset.
         *
         * @param  string  $offset
         * @return void
         */
        public function offsetUnset($offset)
        {
            unset($this->{$offset});
        }

        /**
         * Handle dynamic calls to the container to set attributes.
         *
         * @param  string  $method
         * @param  array   $parameters
         * @return Fluent
         */
        public function __call($method, $parameters)
        {
            $method = Strings::uncamelize($method);

            if ($callable = isAke(self::$macros, $method)) {
                return call_user_func_array($callable, $parameters);
            }

            if (!empty($parameters)) {
                $callable = current($parameters);

                if (is_callable($callable) && $method != 'custom') {
                    self::$macros[$method] = $callable;
                } else {
                    $this->attributes[$method] = $callable;
                }
            } else {
                $this->attributes[$method] = true;
            }

            return $this;
        }

        /**
         * Dynamically retrieve the value of an attribute.
         *
         * @param  string  $key
         * @return mixed
         */
        public function __get($key)
        {
            return $this->get($key);
        }

        /**
         * Dynamically set the value of an attribute.
         *
         * @param  string  $key
         * @param  mixed   $value
         * @return void
         */
        public function __set($key, $value)
        {
            $this->attributes[$key] = $value;
        }

        /**
         * Dynamically check if an attribute is set.
         *
         * @param  string  $key
         * @return void
         */
        public function __isset($key)
        {
            return isset($this->attributes[$key]);
        }

        /**
         * Dynamically unset an attribute.
         *
         * @param  string  $key
         * @return void
         */
        public function __unset($key)
        {
            unset($this->attributes[$key]);
        }

        public function __invoke($key = null)
        {
            if ($key) {
                return isset($this->attributes[$key]) ? $this->attributes[$key] : null;
            } else {
                return o($this->attributes);
            }
        }

        public function toObject()
        {
            return o($this->attributes);
        }

        public function toCollection()
        {
            return coll($this->attributes);
        }
    }
