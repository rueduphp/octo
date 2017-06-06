<?php
    namespace Octo;

    class Auth
    {
        public static function login($user, $ns = 'web')
        {
            session($ns)->setUser($user);
        }

        public static function logout($ns = 'web')
        {
            session($ns)->erase('user');
        }

        public static function id($ns = 'web')
        {
            $user = session($ns)->getUser();

            if ($user) {
                return $user['id'];
            }

            return null;
        }

        public static function email($ns = 'web')
        {
            $user = session($ns)->getUser();

            if ($user) {
                return isAke($user, 'email', null);
            }

            return null;
        }

        public static function user($model = true, $ns = 'web', $em = 'user')
        {
            $user = session($ns)->getUser();

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
