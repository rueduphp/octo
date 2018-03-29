<?php
    namespace Octo;

    use Closure;

    class Guardian
    {
        protected static $policies = [];

        public static function policy($name, Closure $callback)
        {
            if (!isset(static::$policies[$name])) {
                static::$policies[$name] = [];
            }

            static::$policies[$name][] = $callback;
        }

        /**
         * @return bool
         * @throws \ReflectionException
         */
        public static function can()
        {
            $args   = func_get_args();
            $policy = array_shift($args);

            $callbacks = isAke(static::$policies, $policy, []);

            $auth = actual('auth.class');

            if (!$auth) {
                $auth = new Auth;
            }

            $user = $auth->user();

            $a = array_merge([$user], $args);

            if (!empty($callbacks)) {
                foreach ($callbacks as $callback) {
                    $check = call_user_func_array($callback, $a);

                    if (!$check) {
                        return false;
                    }
                }

                return true;
            }

            return false;
        }

        public static function cannot()
        {
            $check = forward_static_call([__CLASS__, 'can'], func_get_args());

            return !$check;
        }
    }
