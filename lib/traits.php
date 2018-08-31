<?php
namespace Octo;

/* Traits */

use BadMethodCallException;
use Closure;
use Exception as PHPException;
use ReflectionClass;
use ReflectionMethod;
use SplFixedArray as FA;

trait Tractor
{
    public function bootTraits()
    {
        $called = get_called_class();
        $traits = class_uses($called);

        foreach ($traits as $trait) {
            $class = str_replace('\\', '_', $trait);

            $function = Inflector::camelize('boot_' . $class);

            try {
                $i =  gi()->singleton($called);
                gi()->call($i, $function);
            } catch (PHPException $e) {}
        }
    }
}

trait Singleton
{
    protected static $instance;

    /**
     * @return mixed|object
     *
     * @throws \ReflectionException
     */
    final public static function instance()
    {
        $called = get_called_class();

        return isset(static::$instance) ?
            static::$instance :
            static::$instance = gi()->singleton($called);
    }

    final protected function __construct()
    {
        $this->init();
    }

    protected function init() {}

    public function __clone()
    {
        trigger_error('Cloning ' . get_called_class() . ' is not allowed.', E_USER_ERROR);
    }

    public function __wakeup()
    {
        trigger_error('Unserializing ' . get_called_class() . ' is not allowed.', E_USER_ERROR);
    }
}

trait Instantiable
{
    /**
     * @return mixed
     *
     * @throws \ReflectionException
     */
    public static function getInstance()
    {
        $class = get_called_class();

        if (!Registry::exists('instances.' . $class)) {
            $ref    = new Reflectionclass($class);
            $args   = func_get_args();
            Registry::set('instances.' . $class, $args
                ? $ref->newinstanceargs($args)
                : gi()->singleton($class)
            );
        }

        return Registry::get('instances.' . $class);
    }
}

trait RepositoryTrait
{
    /**
     * @param array $data
     * @return mixed
     */
    public function getModel(array $data = [])
    {
        if (!isset($this->model)) {
            $this->model = $model = Strings::uncamelize(
                str_replace(
                    'Repository',
                    '',
                    Arrays::last(
                        explode(
                            '\\',
                            get_called_class()
                        )
                    )
                )
            );
        } else {
            $model = $this->model;
        }

        return new $model($data);
    }

    /**
     * @param $method
     * @param $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $model = $this->getModel();

        return $model->{$method}(...$parameters);
    }

    /**
     * @param $method
     * @param $parameters
     *
     * @return mixed
     * 
     * @throws \ReflectionException
     */
    public static function __callStatic($method, $parameters)
    {
        $instance = instanciator()->singleton(get_called_class());

        return $instance->{$method}(...$parameters);
    }
}

trait Notifiable
{
    /**
     * @param mixed ...$args
     * @return array
     */
    public function notify(...$args)
    {
        $class      = array_shift($args);
        $instance   = gi()->factory($class, $this);
        $params     = array_merge([$instance, 'handle'], array_merge([$this], $args));

        $to = gi()->call(...$params);

        if (!is_array($to)) {
            $to = [$to];
        }

        $result = [];

        try {
            foreach ($to as $via) {
                $params = array_merge([$instance, Inflector::camelize('to_' . $via)], array_merge([$this], $args));

                $result[] = gi()->call(...$params);
            }
        } catch (\Exception $e) {
            if (in_array('onFail', get_class_methods($instance))) {
                gi()->call($instance, 'onFail', $this);
            }
        }

        if (in_array('onSuccess', get_class_methods($instance))) {
            gi()->call($instance, 'onSuccess', $this, $result);
        }

        return $result;
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

        return FA::fromArray($cursor);
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

    public static function macro(string $name, $macro)
    {
        static::$macros[$name] = $macro;
    }

    public static function fn(string $name, $macro)
    {
        static::$macros[$name] = $macro;
    }

    /**
     * @param $mixin
     *
     * @throws \ReflectionException
     */
    public static function mixin($mixin)
    {
        $methods = (new ReflectionClass($mixin))->getMethods(
            ReflectionMethod::IS_PRIVATE |
            ReflectionMethod::IS_PUBLIC |
            ReflectionMethod::IS_PROTECTED
        );

        foreach ($methods as $method) {
            $method->setAccessible(true);

            static::macro($method->name, $method->invoke($mixin));
        }
    }

    public static function hasMacro(string $name)
    {
        return isset(static::$macros[$name]);
    }

    /**
     * @param string $method
     * @param array $parameters
     *
     * @return mixed|null
     *
     * @throws \ReflectionException
     */
    public static function __callStatic(string $method, array $parameters)
    {
        if (!static::hasMacro($method)) {
            throw new BadMethodCallException("Method {$method} does not exist.");
        }

        if (static::$macros[$method] instanceof Closure) {
            $params = array_merge(
                [Closure::bind(static::$macros[$method], null, static::class)],
                $parameters
            );

            return gi()->makeClosure(...$params);
        }

        $params = array_merge(
            [static::$macros[$method]],
            $parameters
        );

        return gi()->call(...$params);
    }

    /**
     * @param string $method
     * @param array $parameters
     *
     * @return mixed|null
     *
     * @throws \ReflectionException
     */
    public function __call(string $method, array $parameters)
    {
        if (! static::hasMacro($method)) {
            throw new BadMethodCallException("Method {$method} does not exist.");
        }

        $macro = static::$macros[$method];

        if ($macro instanceof Closure) {
            $params = array_merge(
                [$macro->bindTo($this, static::class)],
                $parameters
            );

            return gi()->makeClosure(...$params);
        }

        $params = array_merge(
            [$macro],
            $parameters
        );

        return gi()->call(...$params);
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

    public function toJson($option = JSON_PRETTY_PRINT)
    {
        return json_encode($this->storage, $option);
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

trait HasDataTrait
{
    /** @var array */
    private $data = [];

    /**
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->data);
    }

    /**
     * @param $offset
     *
     * @return mixed|null
     */
    public function & offsetGet($offset)
    {
        if (isset($this->data[$offset])) {
            return $this->data[$offset];
        }

        $value = null;

        return $value;
    }

    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    public function toArray()
    {
        return $this->data;
    }

    public function toJson($option = JSON_PRETTY_PRINT)
    {
        return json_encode($this->data, $option);
    }

    public function count()
    {
        return count($this->data);
    }
}

trait Sleepable
{
    /**
     * @return array
     * @throws \ReflectionException
     */
    public function __sleep()
    {
        $properties = (new \ReflectionClass($this))->getProperties();

        foreach ($properties as $property) {
            $property->setValue($this, $this->getPropertyValue($property));
        }

        return array_map(function (\ReflectionProperty $p) {
            return $p->getName();
        }, $properties);
    }

    /**
     * @throws \ReflectionException
     */
    public function __wakeup()
    {
        foreach ((new \ReflectionClass($this))->getProperties() as $property) {
            $property->setValue($this, $this->getPropertyValue($property));
        }
    }

    /**
     * @param  \ReflectionProperty  $property
     * @return mixed
     */
    protected function getPropertyValue(\ReflectionProperty $property)
    {
        $property->setAccessible(true);

        return $property->getValue($this);
    }
}

interface isArrayable
{
    public function toArray();
}

interface Processable
{
    public function process(FastRequest $request, callable $next);
}

interface Handable
{
    public function handle(FastRequest $request, callable $next);
}

trait Componentable
{
    public function __invoke($app)
    {
        $return = [];

        $methods = get_class_methods(__CLASS__);

        foreach ($methods as $method) {
            if (!fnmatch('__*', $method)) {
                $callback = function (...$args) use ($method, $app) {
                    $params = array_merge([$this, $method], array_merge([$app], $args));

                    return gi()->call(...$params);
                };

                $return[$method] = $callback;
            }
        }

        return $return;
    }
}

trait Arrayable
{
    /**
     * @var array
     */
    protected $data = [];

    public function __construct($data = null)
    {
        $data = arrayable($data) ? $data->toArray() : $data;

        if (is_array($data) && !empty($data)) {
            $this->fill($data);
        }
    }

    /**
     * @param string $key
     * @param $value
     *
     * @return self
     */
    public function set(string $key, $value): self
    {
        aset($this->data, $key, $value);

        return $this;
    }

    /**
     * @param string $key
     * @param null $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return aget($this->data, $key, $default);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function delete(string $key): bool
    {
        if ($this->has($key)) {
            adel($this->data, $key);

            return true;
        }

        return false;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        return 'octodummy' !== $this->get($key, 'octodummy');
    }

    /**
     * @param array $data
     *
     * @return self
     */
    public function fill(array $data): self
    {
        foreach ($data as $key => $value) {
            $value = arrayable($value) ? $value->toArray() : $value;

            if (is_array($value)) {
                $this->fill($value);
            } else {
                $this->set($key, $value);
            }
        }

        return $this;
    }

    /**
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * @param mixed $offset
     *
     * @return mixed
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
        $this->set($offset, $value);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        $this->delete($offset);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->get($key);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function __unset(string $key): bool
    {
        return $this->delete($key);
    }

    /**
     * @param string $key
     * @param $value
     *
     * @return self
     */
    public function __set(string $key, $value): self
    {
        return $this->set($key, $value);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->data);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return coll($this->data)->{$name}(...$arguments);
    }
}

interface Jsonable
{
    public function toJson($options = 0);
}

trait Eventable
{
    /**
     * @return Listener
     * @throws \ReflectionException
     */
    public function on(...$args): Listener
    {
        return getEventManager()->on(...$args);
    }

    /**
     * @return mixed
     * @throws \ReflectionException
     */
    public function fire(...$args)
    {
        return getEventManager()->fire(...$args);
    }

    /**
     * @param string $event
     *
     * @return bool
     *
     * @throws \ReflectionException
     */
    public function hasEvent(string $event): bool
    {
        return getEventManager()->has($event);
    }
}

trait Hookable
{
    /**
     * The alternative implementation of hooked methods.
     *
     * @var array
     */
    public static $__hooks = [];

    /**
     * @return mixed
     *
     * @throws \ReflectionException
     */
    public static function callHook(...$args)
    {
        $hook = array_shift($args);

        return static::interact($hook, $args);
    }

    /**
     * @param string $hook
     * @param array $parameters
     *
     * @return mixed
     *
     * @throws \ReflectionException
     */
    public static function interact(string $hook, array $parameters = [])
    {
        if (!fnmatch('*@*', $hook)) {
            $hook = $hook . '@handle';
        }

        list($class, $method) = explode('@', $hook);

        if (isset(static::$__hooks[$hook])) {
            return static::callHookedInteraction($hook, $parameters, $class);
        }

        $base = static::base($class);

        if (isset(static::$__hooks[$base . '@' . $method])) {
            return static::callHookedInteraction($base . '@' . $method, $parameters, $class);
        }

        $params = array_merge([app($class), $method],$parameters);

        return instanciator()->call(...$params);
    }

    /**
     * @param string $concern
     *
     * @return string
     */
    protected static function base(string $concern): string
    {
        $concern = is_object($concern) ? get_class($concern) : $concern;

        return basename(str_replace('\\', '/', $concern));
    }

    /**
     * @param string $hook
     * @param array $parameters
     * @param string $class
     *
     * @return mixed
     *
     * @throws \ReflectionException
     */
    protected static function callHookedInteraction(string $hook, array $parameters, string $class)
    {
        if (is_string($closure = static::$__hooks[$hook])) {
            return static::interact($closure, $parameters);
        }

        $instance = app($class);

        /** @var Closure $closure */
        $closure = static::$__hooks[$hook];

        $method = $closure->bindTo($instance, $instance);

        $args = array_merge([$method], $parameters);

        return instanciator()->makeClosure(...$args);
    }

    /**
     * Hook the implementation of an interaction method.
     *
     * @param  string  $interaction
     * @param  mixed  $callback
     *
     * @return void
     */
    public static function hook(string $hook, $callback): void
    {
        static::$__hooks[$hook] = $callback;
    }
}

trait Tapable
{
    /**
     * @param callable|null $callback
     *
     * @return mixed|Tap
     */
    public function tap(?callable $callback = null)
    {
        return tap($this, $callback);
    }
}
