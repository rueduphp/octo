<?php
    namespace Octo;

    class Auth
    {
        public static function get($default = null, $ns = 'web')
        {
            if (session_id()) {
                $user = session($ns)->getUser(actual('auth.user'));
            } else {
                $user = actual('auth.user');
            }

            if ($user) {
                $user = !is_array($user) ? $user->toArray() : $user;
                actual('auth.user', $user);

                return $user;
            }

            return $default;
        }

        public static function make($user = null, $ns = 'web')
        {
            $user = $user ?: static::get($user, $ns);

            if ($user) {
                $user = !is_array($user) ? $user->toArray() : $user;
                actual('auth.user', $user);

                if (session_id()) {
                    session($ns)->setUser($user);
                }
            }
        }

        public static function is($ns = 'web')
        {
            'octodummy' !== static::get('octodummy', $ns);
        }

        public static function guest($ns = 'web')
        {
            'octodummy' === static::get('octodummy', $ns);
        }

        public static function login($user, $ns = 'web')
        {
            $user = !is_array($user) ? $user->toArray() : $user;

            actual('auth.user', $user);

            if (session_id()) {
                session($ns)->setUser($user);
            }
        }

        public static function logout($ns = 'web')
        {
            if (session_id()) {
                session($ns)->erase('user');
            }

            actual('auth.user', null);
        }

        public static function id($ns = 'web')
        {
            $user = static::get(null, $ns);

            if ($user) {
                return $user['id'];
            }

            return null;
        }

        public static function email($ns = 'web')
        {
            $user = static::get(null, $ns);;

            if ($user) {
                return isAke($user, 'email', null);
            }

            return null;
        }

        public static function user($model = true, $ns = 'web', $em = 'user')
        {
            $user = static::get(null, $ns);;

            if ($user && $model) {
                return em($em)->find((int) $user['id']);
            }

            return $user;
        }

        public static function __callStatic($m, $a)
        {
            return call_user_func_array([auth(), $m], $a);
        }
    }
