<?php
    namespace Octo;

    class Authentication
    {
        protected $ns = 'web', $actual = 'auth.user', $entity = 'user';

        protected static function called()
        {
            return actual('auth.class', maker(get_called_class()));
        }

        public static function policy($policy, callable $callable)
        {
            $class              = static::called();
            $policies           = Registry::get('guard.policies.' . $class->actual, []);
            $policies[$policy]  = $callable;

            Registry::set('guard.policies.' . $class->actual, $policies);

            return $class;
        }

        public static function cannot()
        {
            $check = call_user_func_array([static::called(), 'can'], func_get_args());

            return !$check;
        }

        public static function can()
        {
            $check = call_user_func_array([static::called(), 'allows'], func_get_args());

            if ($check) {
                return true;
            }

            return false;
        }

        public static function allows()
        {
            if ($user = static::user(false)) {
                $class      = static::called();
                $user       = item($user);
                $args       = func_get_args();
                $policy     = array_shift($args);
                $policies   = Registry::get('guard.policies.' . $class->actual, []);
                $policy     = isAke($policies, $policy, null);

                if (is_callable($policy)) {
                    return call_user_func_array($policy, array_merge([$user], $args));
                }
            }

            return false;
        }

        public static function get($default = null, $class = null)
        {
            $class = $class ?: static::called();

            $fromSession = false;

            if (session_id()) {
                $user = session($class->ns)
                ->getUser(
                    actual($class->actual)
                );

                $fromSession = !is_null($user);
            } else {
                $user = actual($class->actual);
            }

            if ($user) {
                $user = arrayable($user) ? $user->toArray() : $user;
                $user = item($user);

                actual($class->actual, $user);

                if (session_id() && !$fromSession) {
                    session($class->ns)->setUser($user);
                }

                return $user;
            }

            return $default;
        }

        public static function make($user = null)
        {
            $class = static::called();

            $user = $user ?: static::get($user, $class);

            if ($user) {
                $user = arrayable($user) ? $user->toArray() : $user;
                $user = item($user);
                actual($class->actual, $user);

                if (session_id()) {
                    session($class->ns)->setUser($user);
                }
            }
        }

        public static function is()
        {
            $class = static::called();

            return 'octodummy' !== static::get('octodummy', $class);
        }

        public static function guest()
        {
            $class = static::called();

            return 'octodummy' === static::get('octodummy', $class);
        }

        public static function login($user)
        {
            $class = static::called();

            $user = arrayable($user) ? $user->toArray() : $user;

            $user = item($user);

            actual($class->actual, $user);

            if (session_id()) {
                session($class->ns)->setUser($user);
            }
        }

        public static function logout()
        {
            $class = static::called();

            if (session_id()) {
                session($class->ns)->erase('user');
            }

            actual($class->actual, null);
        }

        public static function id()
        {
            $class = static::called();

            $user = static::get(null, $class);

            if ($user) {
                return $user['id'];
            }

            return null;
        }

        public static function email()
        {
            $class = static::called();

            $user = static::get(null, $class);

            if ($user) {
                return isAke($user, 'email', null);
            }

            return null;
        }

        public static function user($model = true)
        {
            $class = static::called();

            $user = actual($class->actual);

            if (!$user) {
                $user = static::get(null, $class);

                if ($user && $model) {
                    return em($class->entity)->find((int) $user['id']);
                }
            }

            $user = arrayable($user) ? $user->toArray() : $user;

            return item($user);
        }

        public static function __callStatic($m, $a)
        {
            if ($m == "self") {
                return static::called();
            }

            $class = static::called();

            return call_user_func_array([guard($class->ns, $class->entity), $m], $a);
        }
    }
