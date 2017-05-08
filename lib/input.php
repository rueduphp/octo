<?php
    namespace Octo;

    Class Input
    {
        public static function all($k = null, $d = null)
        {
            $all = self::clean($_GET) + self::clean($_POST) + self::clean($_REQUEST);

            if (is_array($k)) {
                return self::only($all, $k);
            }

            return $k ? isAke(oclean($all), $k, value($d)) : o($all);
        }

        public static function post($k = null, $d = null)
        {
            $all = self::clean($_POST);

            if (is_array($k)) {
                return self::only($all, $k);
            }

            return $k ? isAke(oclean($all), $k, value($d)) : o($all);
        }

        public static function get($k = null, $d = null)
        {
            $all = self::clean($_GET);

            if (is_array($k)) {
                return self::only($all, $k);
            }

            return $k ? isAke(oclean($all), $k, value($d)) : o($all);
        }

        public static function request($k = null, $d = null)
        {
            $all = self::clean($_REQUEST);

            if (is_array($k)) {
                return self::only($all, $k);
            }

            return $k ? isAke(oclean($all), $k, value($d)) : o($all);
        }

        public static function session($k = null, $d = null)
        {
            start_session();

            $all = self::clean($_SESSION);

            if (is_array($k)) {
                return self::only($all, $k);
            }

            return $k ? isAke(oclean($all), $k, value($d)) : o($all);
        }

        public static function server($k = null, $d = null)
        {
            $all = self::clean($_SERVER);

            if (is_array($k)) {
                return self::only($all, $k);
            }

            return $k ? isAke(oclean($all), $k, value($d)) : o($all);
        }

        public static function globals($k = null, $d = null)
        {
            $all = self::clean($GLOBALS);

            if (is_array($k)) {
                return self::only($all, $k);
            }

            return $k ? isAke(oclean($all), $k, value($d)) : o($all);
        }

        public static function cookie($k = null, $d = null)
        {
            $all = self::clean($_COOKIE);

            if (is_array($k)) {
                return self::only($all, $k);
            }

            return $k ? isAke(oclean($all), $k, value($d)) : o($all);
        }

        public static function data(array $data = [], $k = null, $d = null)
        {
            $data = empty($data) ? $_POST : $data;

            $all = self::clean($data);

            if (is_array($k)) {
                return self::only($all, $k);
            }

            return $k ? isAke(oclean($all), $k, value($d)) : o($all);
        }

        public static function has($k)
        {
            return 'octodummy' !== self::get($k, 'octodummy');
        }

        public static function count()
        {
            return count($_GET + $_POST + $_REQUEST);
        }

        public static function set($k, $v = null)
        {
            $_REQUEST[$k] = $v;
        }

        public static function clean($data)
        {
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    unset($data[$key]);

                    $data[self::clean($key)] = self::clean($value);
                }
            } else {
                if (ini_get('magic_quotes_gpc')) {
                  $data = stripslashes($data);
                } else {
                  $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
                }
            }

            return $data;
        }

        public static function upload($field)
        {
            return upload($field);
        }

        protected static function only(array $array, array $keys)
        {
            $inputs = [];

            foreach ($keys as $key) {
                $inputs[$key] = isAke($array, $key, null);
            }

            return $inputs;
        }
    }
