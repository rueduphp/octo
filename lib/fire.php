<?php
namespace Octo;

use Closure;

/**
 * @method Listener listen(string $name)
 * @method static fire(string $name)
 * @method static subscribe(string $name)
 **/
class Fire
{
    /**
     * @var string
     */
    protected $ns;

    /**
     * @param string|null $ns
     */
    public function __construct($ns = null)
    {
        $this->ns = $ns ?: Inflector::urlize(get_called_class());
    }

    /**
     * @return string
     */
    public function ns()
    {
        return $this->ns;
    }

    /**
     * @return mixed|object
     *
     * @throws \ReflectionException
     */
    protected static function called()
    {
        return gi()->make(get_called_class());
    }

    /**
     * @return Fire
     */
    public function self(): self
    {
        return $this;
    }

    /**
     * @param $event
     * @param $callable
     * @param int $priority
     *
     * @return Listener
     *
     * @throws \ReflectionException
     */
    public function on($event, $callable, $priority = 0)
    {
        $events = Registry::get('fire.events.' . $this->ns, []);

        if (!isset($events[$event])) {
            $events[$event] = [];
        }

        $priority = !is_int($priority) ? 0 : $priority;

        if (!is_callable($callable) && !class_exists($callable)) {
            $callable = resolverClass($callable);
        }

        $ev = $events[$event][] = new Listener($callable, $priority);

        Registry::set('fire.events.' . $this->ns, $events);

        return $ev;
    }

    /**
     * @param string $event
     *
     * @return bool
     */
    public function has(string $event): bool
    {
        $events = Registry::get('fire.events.' . $this->ns, []);

        return 'octodummy' !== isAke($events, $event, 'octodummy');
    }

    /**
     * @param string $event
     * @return bool
     */
    public function delete(string $event)
    {
        if ($this->has($event)) {
            $events = Registry::get('fire.events.' . $this->ns, []);
            unset($events[$event]);
            Registry::set('fire.events.' . $this->ns, $events);

            return true;
        }

        return false;
    }

    /**
     * @param mixed ...$args
     * @return array|mixed|null
     */
    public function emit(...$args)
    {
        $event  = array_shift($args);

        $events = Registry::get('fire.events.' . $this->ns, []);

        $eventsToCall = isAke($events, $event, []);

        if (!empty($eventsToCall)) {
            $results    = [];
            $collection = [];

            foreach ($eventsToCall as $eventToCall) {
                $collection[] = [
                    'event'     => $eventToCall,
                    'priority'  => (int) $eventToCall->priority
                ];
            }

            $listeners = coll($collection)->sortByDesc('priority')->all();

            foreach ($listeners as $listenerCalled) {
                $result = null;
                $listener = $listenerCalled['event'];

                $continue = true;

                if ($listener->called) {
                    if ($listener->once === true) {
                        $continue = false;
                    }
                }

                if (!$continue) {
                    break;
                } else {
                    $listener->called = true;
                    actual('fired.event', $listener);

                    if (is_object($listener->callable) && is_invokable(get_class($listener->callable))) {
                        $params = array_merge([$listener->callable, '__invoke'], $args);

                        try {
                            $result = gi()->call(...$params);

                            if (method_exists($listener->callable, 'onSuccess')) {
                                gi()->call($listener->callable, 'onSuccess', $result);
                            }
                        } catch (\Exception $e) {
                            if (method_exists($listener->callable, 'onFail')) {
                                return gi()->call($listener->callable, 'onFail');
                            }
                        }
                    } else {
                        if ($listener->callable instanceof Closure) {
                            $params = array_merge([$listener->callable], $args);
                            $result = gi()->makeClosure(...$params);
                        } elseif (is_array($listener->callable)) {
                            $params = array_merge($listener->callable, $args);
                            $result = gi()->call(...$params);
                        } elseif (is_string($listener->callable) && class_exists($listener->callable)) {
                            $instance = gi()->make($listener->callable);
                            $params = array_merge([$instance, 'handle'], $args);

                            try {
                                $result = gi()->call(...$params);

                                if (method_exists($instance, 'onSuccess')) {
                                    gi()->call($instance, 'onSuccess', $result);
                                }
                            } catch (\Exception $e) {
                                if (method_exists($instance, 'onFail')) {
                                    return gi()->call($instance, 'onFail');
                                }
                            }
                        }
                    }

                    if ($listener->halt) {
                        Registry::set('fire.events.' . $this->ns, []);

                        return $result;
                    } else {
                        $results[] = $result;
                    }
                }
            }

            return count($results) === 1 ? reset($results) : $results;
        }

        return null;
    }

    /**
     * @param $class
     * @return Listener[]
     * @throws \ReflectionException
     */
    public function subscriber($class)
    {
        $collection = [];

        /** @var FastEventSubscriberInterface $instance */
        $instance = gi()->make($class);

        /** @var array $events */
        $events = $instance->getEvents();

        foreach ($events as $event => $method) {
            if (is_string($method)) {
                $ev = $this->on($event, [$instance, $method]);
            } elseif (is_callable($method)) {
                $ev = $this->on($event, $method);
            } else {
                $ev = $this->on($event, [$instance, $method->getMethod()], $method->getPriority(0));

                if ($halt = $method->getHalt()) {
                    $ev->halt($halt);
                }

                if ($once = $method->getOnce()) {
                    $ev->once($once);
                }
            }

            $collection[] = $ev;
        }

        return $collection;
    }

    /**
     * @param $m
     * @param $a
     *
     * @return mixed
     *
     * @throws \ReflectionException
     */
    public static function __callStatic($m, $a)
    {
        return static::called()->{$m}(...$a);
    }

    /**
     * @param $m
     * @param $a
     *
     * @return mixed
     */
    public function __call($m, $a)
    {
        if ($m === 'fire') {
            $m = 'emit';
        } elseif ($m === 'subscribe') {
            $m = 'subscriber';
        } elseif ($m === 'listen') {
            $m = 'on';
        }

        return $this->{$m}(...$a);
    }

    /**
     * @param  string $eventName
     * @return bool
     */
    public function hasListeners($eventName)
    {
        $events = Registry::get('fire.events.' . $this->ns, []);

        return 'octodummy' !== isAke($events, $eventName, 'octodummy');
    }

    /**
     * @param object|string $event
     * @param array $payload
     * @return void
     * @throws \ReflectionException
     */
    public function until($event, $payload = [])
    {
        $events = Registry::get('fire.events.' . $this->ns, []);

        $ev = $events[$event];

        if ($ev instanceof Listener) {
            $ev->halt(true);
            $events[$event] = $ev;
            Registry::set('fire.events.' . $this->ns, $events);
            $this->emit(...func_get_args());
        }
    }

    /**
     * @param object|string $event
     * @param array $payload
     * @param bool $halt
     *
     * @return array|null|void
     *
     * @throws \ReflectionException
     */
    public function dispatch($event, $payload = [], $halt = false)
    {
        $this->emit(...func_get_args());
    }

    /**
     * @param string $event
     * @param array $payload
     *
     * @throws \ReflectionException
     */
    public function push($event, $payload = [])
    {
        $this->on(...func_get_args());
    }

    /**
     * @throws \ReflectionException
     */
    public function flush()
    {
        $events = Registry::get('fire.events.' . $this->ns, []);

        foreach ($events as $event) {
            $this->emit($event);
        }
    }

    /**
     * @param  string $event
     * @return void
     */
    public function forget($event)
    {
        $events = Registry::get('fire.events.' . $this->ns, []);
        unset($events[$event]);
        Registry::set('fire.events.' . $this->ns, $events);
    }

    /**
     * @return void
     */
    public function forgetPushed()
    {
        Registry::set('fire.events.' . $this->ns, []);
    }

    /**
     * @param string $method
     * @param int $priority
     * @param bool $halt
     * @param bool $once
     * @return Objet
     */
    public static function makeEvent(
        string $method,
        int $priority = 0,
        bool $halt = false,
        bool $once = false
    ): Objet {
        return o(compact('method', 'priority', 'halt', 'once'));
    }
}
