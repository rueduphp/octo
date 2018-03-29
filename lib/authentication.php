<?php
    namespace Octo;

    class Authentication
    {
        protected $ns = 'web', $actual = 'auth.user', $entity = 'user';

        /**
         * @return Authentication
         *
         * @throws \ReflectionException
         */
        protected static function called(): Authentication
        {
            return actual('auth.class', instanciator()->singleton(get_called_class()));
        }

        /**
         * @param string $policy
         * @param callable $callable
         *
         * @return Authentication
         *
         * @throws \ReflectionException
         */
        public static function policy(string $policy, callable $callable)
        {
            $class              = static::called();
            $policies           = Registry::get('guard.policies.' . $class->actual, []);
            $policies[$policy]  = $callable;

            Registry::set('guard.policies.' . $class->actual, $policies);

            return $class;
        }

        /**
         * @return bool
         *
         * @throws \ReflectionException
         */
        public static function cannot(): bool
        {
            $check = call_user_func_array([static::called(), 'can'], func_get_args());

            return !$check;
        }

        /**
         * @return bool
         *
         * @throws \ReflectionException
         */
        public static function can(): bool
        {
            $check = call_user_func_array([static::called(), 'allows'], func_get_args());

            if ($check) {
                return true;
            }

            return false;
        }

        /**
         * @return bool
         *
         * @throws \ReflectionException
         */
        public static function allows(...$args): bool
        {
            if ($user       = static::user()) {
                $user       = arrayable($user) ? $user->toArray() : $user;
                $class      = static::called();
                $user       = item($user);
                $policy     = array_shift($args);
                $policies   = Registry::get('guard.policies.' . $class->actual, []);
                $policy     = isAke($policies, $policy, null);

                if (is_callable($policy)) {
                    return call_user_func_array($policy, array_merge([$user], $args));
                }
            }

            return false;
        }

        /**
         * @param null $default
         * @param null $class
         *
         * @return mixed|null|object
         *
         * @throws \ReflectionException
         */
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

        /**
         * @param null $user
         *
         * @throws \ReflectionException
         */
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

        /**
         * @return bool
         *
         * @throws \ReflectionException
         */
        public static function is()
        {
            $class = static::called();

            return 'octodummy' !== static::get('octodummy', $class);
        }

        /**
         * @return bool
         *
         * @throws \ReflectionException
         */
        public static function guest()
        {
            $class = static::called();

            return 'octodummy' === static::get('octodummy', $class);
        }

        /**
         * @param $user
         * @throws \ReflectionException
         */
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

        /**
         * @param $id
         * @throws \ReflectionException
         */
        public static function loginWithId($id)
        {
            $class = static::called();

            $user = em($class->entity)->findOrFail((int) $id);

            $user = arrayable($user) ? $user->toArray() : $user;

            $user = item($user);

            actual($class->actual, $user);

            if (session_id()) {
                session($class->ns)->setUser($user);
            }
        }

        /**
         * @throws \ReflectionException
         */
        public static function logout()
        {
            $class = static::called();

            if (session_id()) {
                session($class->ns)->erase('user');
            }

            actual($class->actual, null);
        }

        /**
         * @return null
         * @throws \ReflectionException
         */
        public static function id()
        {
            $class = static::called();

            $user = static::get(null, $class);

            if ($user) {
                return $user['id'];
            }

            return null;
        }

        /**
         * @return null|string
         * @throws \ReflectionException
         */
        public static function email(): ?string
        {
            $class = static::called();

            $user = static::get(null, $class);

            if ($user) {
                return isAke($user, 'email', null);
            }

            return null;
        }

        /**
         * @param bool $model
         *
         * @return mixed|null|object
         *
         * @throws \ReflectionException
         */
        public static function user(bool $model = true)
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

            return !empty($user) ? item($user) : null;
        }

        /**
         * @param string $m
         * @param array $a
         *
         * @return mixed|Authentication
         *
         * @throws \ReflectionException
         */
        public static function __callStatic(string $m, array $a)
        {
            if ($m === "self") {
                return static::called();
            }

            $class = static::called();

            return call_user_func_array([guard($class->ns, $class->entity), $m], $a);
        }
    }
