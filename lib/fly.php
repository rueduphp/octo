<?php
    namespace Octo;

    class Fly
    {
        protected static $events = [];

        /**
         * @throws \ReflectionException
         */
        public static function push()
        {
            $args       = func_get_args();
            $event      = array_shift($args);
            $call       = array_shift($args);
            $priority   = array_shift($args);
            $priority   = $priority || 0;

            if (!$call instanceof \Closure) {
                $call = resolverClass($call);
            }

            $priorities = isAke(self::$events, $event, []);

            $segment = isset($priorities[$priority]) ? $priorities[$priority] : [];

            $segment[] = $call;

            if (!isset(self::$events[$event])) {
                self::$events[$event] = [];
            }

            self::$events[$event][$priority] = $segment;
        }

        public static function listen()
        {
            $args = func_get_args();

            $event = array_shift($args);

            $fireEvents = isAke(self::$events, $event, []);

            $results = [];

            if (!empty($fireEvents)) {
                foreach ($fireEvents as $priority => $eventsLoaded) {
                    $key = $event . '_' . $priority;

                    $results[$key] = [];

                    foreach ($eventsLoaded as $eventLoaded) {
                        if ($eventLoaded && is_callable($eventLoaded)) {
                            $result = call_user_func_array($eventLoaded, $args);

                            if ($result instanceof Object && $result->getStopPropagation() == 1) {
                                return $result->getValue();
                            }

                            $results[$key][] = $result;
                        }
                    }
                }
            }

            return $results;
        }

        public static function forget($event, $priority = 'octodummy')
        {
            if (isset(self::$events[$event])) {
                if ('octodummy' === $priority) {
                    unset(self::$events[$event]);
                } else {
                    unset(self::$events[$event][$priority]);
                }
            }
        }

        public static function has($event)
        {
            return isset(self::$events[$event]);
        }

        public static function returnValue($value)
        {
            return o([
                'stop_propagation'  => 1,
                'value'             => $value
            ]);
        }

        public static function halt($value)
        {
            return static::returnValue($value);
        }

        public static function __callStatic($m, $a)
        {
            if (in_array($m, ['emit'])) {
                forward_static_call_array(['\\Octo\\Fly', 'listen'], $a);
            } elseif (in_array($m, ['on', 'register'])) {
                forward_static_call_array(['\\Octo\\Fly', 'push'], $a);
            } else {
                $event  = Strings::uncamelize($m);
                $args   = array_merge([$event], $a);

                if (static::has($event)) {
                    return forward_static_call_array(['\\Octo\\Fly', 'listen'], $args);
                } else {
                    forward_static_call_array(['\\Octo\\Fly', 'push'], $a);
                }
            }
        }
    }
