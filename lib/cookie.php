<?php
    namespace Octo;

    class Cookie
    {
        public static function set($name, $value, $expire = 31536000, $path = '/')
        {
            setcookie($name, $value, time() + $expire, $path);
        }

        public static function get($name, $default = null)
        {
            return isAke($_COOKIE, $name, $default);
        }

        public static function delete()
        {
            $cookies = func_get_args();

            foreach ($cookies as $ck) setcookie($ck, '', -10, '/');
        }
    }
