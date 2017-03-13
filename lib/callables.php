<?php
    namespace Octo;

    class Callables
    {
        public static function __callStatic($m, $a)
        {
            $key = str_replace('_', '.', Strings::uncamelize($m));

            $callables = Registry::get('core.callables');

            $callable = isAke($callables, $key, null);

            if (!$callable) {
                $closure = current($a);

                if (is_callable($closure)) {
                    $callables[$key] = $closure;

                    Registry::set('core.callables', $callables);
                }
            } else {
                if (is_callable($callable)) {
                    $callable = $callable->bindTo(App::getInstance());

                    return call_user_func_array($callable, $a);
                }
            }

            return null;
        }
    }
