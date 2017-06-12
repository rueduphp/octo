<?php
    namespace Octo;

    class Authentication
    {
        protected $ns = 'web', $actual = 'auth.user', $entity = 'user';

        protected static function called()
        {
            return maker(get_called_class());
        }

        public static function get($default = null)
        {
            $class = static::called();

            if (session_id()) {
                $user = session($class->ns)
                ->getUser(
                    actual($class->actual)
                );
            } else {
                $user = actual($class->actual);
            }

            if ($user) {
                $user = !is_array($user) ? $user->toArray() : $user;
                actual($class->actual, $user);

                return $user;
            }

            return $default;
        }

        public static function make($user = null)
        {
            $class = static::called();

            $user = $user ?: static::get($user, $class->ns);

            if ($user) {
                $user = !is_array($user) ? $user->toArray() : $user;
                actual($class->actual, $user);

                if (session_id()) {
                    session($class->ns)->setUser($user);
                }
            }
        }

        public static function is()
        {
            $class = static::called();

            return 'octodummy' !== static::get('octodummy', $class->ns);
        }

        public static function guest()
        {
            $class = static::called();

            return 'octodummy' === static::get('octodummy', $class->ns);
        }

        public static function login($user)
        {
            $class = static::called();

            $user = !is_array($user) ? $user->toArray() : $user;

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

            $user = static::get(null, $class->ns);

            if ($user) {
                return $user['id'];
            }

            return null;
        }

        public static function email()
        {
            $class = static::called();

            $user = static::get(null, $class->ns);

            if ($user) {
                return isAke($user, 'email', null);
            }

            return null;
        }

        public static function user($model = true)
        {
            $class = static::called();

            $user = static::get(null, $class->ns);

            if ($user && $model) {
                return em($class->entity)->find((int) $user['id']);
            }

            return $user;
        }

        public static function __callStatic($m, $a)
        {
            $class = static::called();

            return call_user_func_array([auth($class->ns, $class->entity), $m], $a);
        }
    }
