<?php
    namespace Octo;
    use function get_called_class;

    /**
     * @method fire()
     **/
    class Fire
    {
        private $ns;

        public function __construct($ns = null)
        {
            $this->ns = $ns ?: get_called_class();
        }

        public function ns()
        {
            return $this->ns;
        }

        protected static function called()
        {
            return actual('fire.class', maker(get_called_class(), [get_called_class()]));
        }

        public function on($event, $callable, $priority = 0)
        {
            $events = Registry::get('fire.events.' . $this->ns, []);

            if (!isset($events[$event])) {
                $events[$event] = [];
            }

            $priority = !is_int($priority) ? 0 : $priority;

            if (!is_callable($callable)) {
                $callable = resolverClass($callable);
            }

            $ev = $events[$event][] = new Listener($callable, $priority);

            Registry::set('fire.events.' . $this->ns, $events);

            return $ev;
        }

        /**
         * @param $event
         * @return bool
         */
        public function has($event)
        {
            $events = Registry::get('fire.events.' . $this->ns, []);

            return 'octodummy' !== isAke($events, $event, 'octodummy');
        }

        /**
         * @param $event
         * @return bool
         */
        public function delete($event)
        {
            if ($this->has($event)) {
                $events = Registry::get('fire.events.' . $this->ns, []);
                unset($events[$event]);
                Registry::set('fire.events.' . $this->ns, $events);

                return true;
            }

            return false;
        }

        public function emit()
        {
            $args   = func_get_args();
            $event  = array_shift($args);

            $events = Registry::get('fire.events.' . $this->ns, []);

            $eventsToCall = isAke($events, $event, []);

            if (!empty($eventsToCall)) {
                $collection = [];

                foreach ($eventsToCall as $eventToCall) {
                    $collection[] = [
                        'event'     => $eventToCall,
                        'priority'  => (int) $eventToCall->priority
                    ];
                }

                $listeners = array_values(coll($collection)->sortByDesc('priority')->toArray());

                $results = [];

                foreach ($listeners as $listenerCalled) {
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
                        $result = call_user_func_array($listener->callable, $args);

                        if ($listener->halt) {
                            Registry::set('fire.events.' . $this->ns, []);

                            return $result;
                        } else {
                            $results[] = $result;
                        }
                    }
                }

                return $results;
            }

            return null;
        }

        public function subscriber($class)
        {
            $instance = maker($class);

            $events = $instance->getEvents();

            foreach ($events as $event => $method) {
                if (is_string($method)) {
                    $this->on($event, [$instance, $method]);
                } else {
                    $ev = $this->on($event, [$instance, $method->getMethod()], $method->getPriority(0));

                    if ($method->getHalt()) {
                        $ev->halt();
                    }

                    if ($method->getOnce()) {
                        $ev->once();
                    }
                }
            }
        }

        public static function __callStatic($m, $a)
        {
            $instance = static::called();

            if ($m == 'listen') {
                $m = 'on';
            } elseif ($m == 'fire') {
                $m = 'emit';
            } elseif ($m == 'subscribe') {
                $m = 'subscriber';
            }

            return call_user_func_array([$instance, $m], $a);
        }

        public function __call($m, $a)
        {
            if ($m == 'listen') {
                $m = 'on';
            } elseif ($m == 'fire') {
                $m = 'emit';
            } elseif ($m == 'subscribe') {
                $m = 'subscriber';
            }

            return call_user_func_array([$this, $m], $a);
        }
    }
