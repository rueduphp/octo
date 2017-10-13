<?php
    namespace Octo;

    class Bus
    {
        public function set($event, $closure)
        {
            if (is_string($closure)) {
                $closure = resolverClass($closure);
            }

            $bus = Registry::get('core.bus', []);
            $bus[$event] = $closure;
            Registry::set('core.bus', $bus);

            return $this;
        }

        public function push($event, $closure)
        {
            return $this->set($event, $closure);
        }

        public function get($event, $default = null)
        {
            $bus = Registry::get('core.bus', []);

            $closure = isAke($bus, $event, null);

            if ($closure) {
                if (is_callable($closure)) {
                    return $closure;
                }
            }

            return $default;
        }

        public function has($event)
        {
            $bus = Registry::get('core.bus', []);

            $closure = isAke($bus, $event, null);

            if ($closure) {
                if (is_callable($closure)) {
                    return true;
                }
            }

            return false;
        }

        public function remove($event)
        {
            $bus = Registry::get('core.bus', []);

            $closure = isAke($bus, $event, null);

            if ($closure) {
                unset($bus[$event]);
                Registry::set('core.bus', $bus);

                return true;
            }

            return false;
        }

        public function unpush($event)
        {
            return $this->remove($event);
        }

        public function all()
        {
            return Registry::get('core.bus', []);
        }

        public static function listen()
        {
            $bus = Registry::get('core.bus', []);

            $res = null;

            foreach ($bus as $event) {
                if (is_callable($event)) {
                    $res = $event($res);
                }
            }

            return $res;
        }
    }
