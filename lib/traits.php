<?php
    namespace Octo;

    /* Traits */

    use BadMethodCallException;

    trait Tractor
    {
        public function bootTrait()
        {
            $traits = class_uses();

            foreach ($traits as $trait) {
                $class = str_replace('\\', '_', $trait);

                $function = Inflector::camelize('boot_' . $class);

                try {
                    self::$function();
                } catch (\Exception $e) {}
            }
        }
    }

    trait Singleton
    {
        protected static $instance;

        final public static function instance()
        {
            return isset(static::$instance) ? static::$instance : static::$instance = new static;
        }

        final protected function __construct()
        {
            $this->init();
        }

        protected function init() {}

        public function __clone()
        {
            trigger_error('Cloning ' . __CLASS__ . ' is not allowed.', E_USER_ERROR);
        }

        public function __wakeup()
        {
            trigger_error('Unserializing ' . __CLASS__ . ' is not allowed.', E_USER_ERROR);
        }
    }

    trait Instantiable
    {
        public static function getInstance()
        {
            $class = get_called_class();

            if (!Registry::exists('instances.' . $class)) {
                $ref    = new \Reflectionclass($class);
                $args   = func_get_args();
                Registry::set('instances.' . $class, $args ? $ref->newinstanceargs($args) : new $class);
            }

            return Registry::get('instances.' . $class);
        }
    }

    trait Notifiable
    {
        public function notify($driver = null)
        {
            return new Notification($driver);
        }
    }

    trait Emitter
    {
        protected $emitterSingleEventCollection = [];
        protected $emitterEventCollection       = [];
        protected $emitterEventSorted           = [];

        public function bindEvent($event, $callback, $priority = 0)
        {
            $this->emitterEventCollection[$event][$priority][] = $callback;
            unset($this->emitterEventSorted[$event]);

            return $this;
        }

        public function bindEventOnce($event, $callback)
        {
            $this->emitterSingleEventCollection[$event][] = $callback;

            return $this;
        }

        protected function emitterEventSortEvents($eventName)
        {
            $this->emitterEventSorted[$eventName] = [];

            if (isset($this->emitterEventCollection[$eventName])) {
                krsort($this->emitterEventCollection[$eventName]);

                $this->emitterEventSorted[$eventName] = call_user_func_array('array_merge', $this->emitterEventCollection[$eventName]);
            }
        }

        public function unbindEvent($event = null)
        {
            if (is_array($event)) {
                foreach ($event as $_event) {
                    $this->unbindEvent($_event);
                }

                return;
            }

            if ($event === null) {
                unset($this->emitterSingleEventCollection);
                unset($this->emitterEventCollection);
                unset($this->emitterEventSorted);

                return $this;
            }

            if (isset($this->emitterSingleEventCollection[$event])) unset($this->emitterSingleEventCollection[$event]);

            if (isset($this->emitterEventCollection[$event])) unset($this->emitterEventCollection[$event]);

            if (isset($this->emitterEventSorted[$event])) unset($this->emitterEventSorted[$event]);

            return $this;
        }

        public function fireEvent($event, $params = [], $halt = false)
        {
            if (!is_array($params)) $params = [$params];

            $result = [];

            if (isset($this->emitterSingleEventCollection[$event])) {
                foreach ($this->emitterSingleEventCollection[$event] as $callback) {
                    $response = call_user_func_array($callback, $params);

                    if (is_null($response)) continue;
                    if ($halt) return $response;

                    $result[] = $response;
                }

                unset($this->emitterSingleEventCollection[$event]);
            }

            if (isset($this->emitterEventCollection[$event])) {
                if (!isset($this->emitterEventSorted[$event])) $this->emitterEventSortEvents($event);

                foreach ($this->emitterEventSorted[$event] as $callback) {
                    $response = call_user_func_array($callback, $params);
                    if (is_null($response)) continue;
                    if ($halt) return $response;
                    $result[] = $response;
                }
            }

            return $halt ? null : $result;
        }
    }

    trait Iteractor
    {
        protected $_resource, $_count, $_position = 0;

        public function count($return = true)
        {
            if (!isset($this->_count) || is_null($this->_count)) {
                $this->_count = count($this->getIterator());
            }

            return $return ? $this->_count : $this;
        }

        public function rewind()
        {
            $this->_position = 0;
        }

        public function key()
        {
            return $this->_position;
        }

        public function next()
        {
            ++$this->_position;
        }

        public function valid()
        {
            $cursor = $this->getIterator();

            return isset($cursor[$this->_position]);
        }

        public function seek($pos = 0)
        {
            $this->_position = $pos;

            return $this;
        }

        public function one()
        {
            return $this->seek()->current();
        }


        public function makeIterator($data)
        {
            $this->makeResource($data);
        }

        private function makeResource($cursor)
        {
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $this->_resource = Arrays::makeResource($cursor);
        }

        public function getIterator()
        {
            $cursor = Arrays::makeFromResource($this->_resource);
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            return \SplFixedArray::fromArray($cursor);
        }

        public function each(callable $closure)
        {
            $row = $this->getNext();

            if ($row) {
                return $closure($row);
            }

            return false;
        }
    }

    trait Macroable
    {
        protected static $macros = [];

        public static function macro($name, callable $macro)
        {
            static::$macros[$name] = $macro;
        }

        public static function hasMacro($name)
        {
            return isset(static::$macros[$name]);
        }

        public static function __callStatic($method, $parameters)
        {
            if (static::hasMacro($method)) {
                if (static::$macros[$method] instanceof \Closure) {
                    return call_user_func_array(\Closure::bind(static::$macros[$method], null, get_called_class()), $parameters);
                } else {
                    return call_user_func_array(static::$macros[$method], $parameters);
                }
            } else {
                if (!empty($parameters)) {
                    $callable = current($parameters);

                    if (is_callable($callable)) {
                        static::$macros[$method] = $callable;
                    }
                }
            }

            throw new \BadMethodCallException("Method {$method} does not exist.");
        }

        public function __call($method, $parameters)
        {
            if (static::hasMacro($method)) {
                if (self::$macros[$method] instanceof \Closure) {
                    $args = array_merge([$this], $parameters);

                    return call_user_func_array(static::$macros[$method], $args);
                } else {
                    return call_user_func_array(static::$macros[$method], $parameters);
                }
            } else {
                if (!empty($parameters)) {
                    $callable = current($parameters);

                    if (is_callable($callable)) {
                        self::$macros[$method] = $callable;
                    }
                }
            }

            throw new \BadMethodCallException("Method {$method} does not exist.");
        }
    }

    trait Setters
    {
        private $storage = [];

        public function __set($k, $v)
        {
            return $this->set($k, $v);
        }

        public function __get($k)
        {
            return $this->get($k);
        }

        public function __isset($k)
        {
            return $this->has($k);
        }

        public function __unset($k)
        {
            return $this->delete($k);
        }

        public function set($k, $v)
        {
            $this->storage[$k] = $v;

            return $this;
        }

        public function get($k, $d = null)
        {
            return isAke($this->storage, $k, $d);
        }

        public function has($k)
        {
            $check = sha1(time());

            return $this->get($k, $check) != $check;
        }

        public function fill($data = [], $merge = true)
        {
            $data = $merge ? array_merge($this->storage, $data) : $data;

            $this->storage = $data;

            return $this;
        }

        public function populate($data = [])
        {
            foreach ($data as $k => $v) {
                $this->set($k, $v);
            }

            return $this;
        }

        public function hydrate($data = [])
        {
            return $this->fill($data, false);
        }

        public function incr($k, $by = 1)
        {
            $old = $this->get($k, 0);
            $new = $old + $by;

            $this->set($k, $new);

            return (int) $new;
        }

        public function increment($k, $by = 1)
        {
            return $this->incr($k, $by);
        }

        public function decr($k, $by = 1)
        {
            $old = $this->get($k, 1);
            $new = $old - $by;

            $this->set($k, $new);

            return (int) $new;
        }

        public function toArray()
        {
            return $this->storage;
        }

        public function toJson()
        {
            return json_encode($this->storage);
        }

        public function __toString()
        {
            return $this->toJson();
        }

        public function __call($m, $a)
        {
            $k = Inflector::uncamelize(substr($m, 3));

            if (fnmatch('get*', $m)) {
                $default = empty($a) ? null : current($a);

                return $this->get($k, $default);
            } elseif (fnmatch('set*', $m)) {
                return $this->set($k, current($a));
            } elseif (fnmatch('has*', $m)) {
                return $this->has($k);
            } else {
                $v = !empty($a) ? current($a) : true;

                return $this->set($m, $v);
            }
        }
    }

    trait Throttable
    {
        protected function hasTooManyAttempts()
        {
            return lib('limiter')->tooManyAttempts(
                $this->getThrottleKey(),
                $this->maxAttempts(),
                $this->lockoutTime() / 60
            );
        }

        protected function incrementAttempts()
        {
            lib('limiter')->hit(
                $this->getThrottleKey()
            );
        }

        protected function retriesLeft()
        {
            return lib('limiter')->retriesLeft(
                $this->getThrottleKey(),
                $this->maxAttempts()
            );
        }

        protected function sendLockoutResponse()
        {
            $seconds = $this->secondsRemainingOnLockout();

            $message = Config::get(
                'throttle.response.lockout',
                'You are locked. Please retry again in ' . $seconds . ' seconds.'
            );

            return str_replace('##seconds##', $seconds, $message);
        }

        protected function getLockoutErrorMessage($seconds)
        {
            $message = Config::get(
                'throttle.error.lockout',
                'Too many login attempts. Please try again in ' . $seconds . ' seconds.'
            );

            return str_replace('##seconds##', $seconds, $message);
        }

        protected function secondsRemainingOnLockout()
        {
            return lib('limiter')->availableIn(
                $this->getThrottleKey()
            );
        }

        protected function clearAttempts()
        {
            lib('limiter')->clear(
                $this->getThrottleKey()
            );
        }

        protected function getThrottleKey()
        {
            return sha1(forever() . get_called_class());
        }

        protected function maxAttempts()
        {
            return property_exists($this, 'maxAttempts') ? $this->maxAttempts : Config::get('throttle.attempts', 5);
        }

        protected function lockoutTime()
        {
            return property_exists($this, 'lockoutTime') ? $this->lockoutTime : Config::get('throttle.lockout', 60);
        }

        protected function fireLockoutEvent()
        {
            lib('event')->fire('lockout_' . sha1(get_called_class()));
        }

        protected function setLockoutEvent(callable $cb)
        {
            lib('event')->set('lockout_' . sha1(get_called_class()), $cb);

            return $this;
        }
    }
