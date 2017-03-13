<?php
    namespace Octo;

    class Event
    {
        public function set($event, callable $closure)
        {
            $events = Registry::get('core.events', []);
            $events[$event] = $closure;
            Registry::set('core.events', $events);

            return $this;
        }

        public function get($event, $default = null)
        {
            $events = Registry::get('core.events', []);

            $closure = isAke($events, $event, null);

            if ($closure) {
                if (is_callable($closure)) {
                    return $closure;
                }
            }

            return $default;
        }

        public function has($event)
        {
            $events = Registry::get('core.events', []);

            $closure = isAke($events, $event, null);

            if ($closure) {
                if (is_callable($closure)) {
                    return true;
                }
            }

            return false;
        }

        public function remove($event)
        {
            $events = Registry::get('core.events', []);

            $closure = isAke($events, $event, null);

            if ($closure) {
                unset($events[$event]);
                Registry::set('core.events', $events);

                return true;
            }

            return false;
        }

        public function fire($event, $parameters = [])
        {
            $events = Registry::get('core.events', []);

            $closure = isAke($events, $event, null);

            if ($closure) {
                return call_user_func_array($closure, $parameters);
            }

            return null;
        }

        public function __call($m, $a)
        {
            if ("on" == $m) {
                return call_user_func_array([$this, 'set'], $a);
            }

            if (count($a) == 1) {
                return $this->set(Strings::uncamelize($m), current($a));
            } else {
                $events = Registry::get('core.events', []);

                $closure = isAke($events, Strings::uncamelize($m), null);

                if ($closure) {
                    return call_user_func_array($closure, $parameters);
                }
            }
        }
    }
