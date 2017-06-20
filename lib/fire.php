<?php
    namespace Octo;

    class Fire
    {
        private $ns;

        public function __construct($ns = 'core')
        {
            $this->ns = $ns;
        }

        public function ns()
        {
            return $this->ns;
        }

        protected static function called()
        {
            return actual('fire.class', maker(get_called_class(), [get_called_class()]));
        }

        public function on($event, callable $callable, $priority = 0)
        {
            $events = Registry::get('fire.events.' . $this->ns, []);

            if (!isset($events[$event])) {
                $events[$event] = [];
            }

            $priority = !is_int($priority) ? 0 : $priority;

            $ev = $events[$event][] = new Listener($callable, $priority);

            Registry::set('fire.events.' . $this->ns, $events);

            return $ev;
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

        public static function __callStatic($m, $a)
        {
            $instance = static::called();

            if ($m == 'listen') {
                $m = 'on';
            }

            if ($m == 'fire') {
                $m = 'emit';
            }

            return call_user_func_array([$instance, $m], $a);
        }

        public function __call($m, $a)
        {
            if ($m == 'listen') {
                $m = 'on';
            }

            if ($m == 'fire') {
                $m = 'emit';
            }

            return call_user_func_array([$this, $m], $a);
        }
    }
