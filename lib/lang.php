<?php
    namespace Octo;

    class Lang
    {
        public static $items = [];

        public static function load($file, $lng)
        {
            if (Registry::has('lang.load.' . $lng)) {
                return;
            }

            if (File::exists($file)) {
                self::segment($lng);

                $dictionary = include $file;

                foreach($dictionary as $key => $value) {
                    static::$items[$lng][$key] = $value;
                }

                Registry::set('lang.load.' . $lng, true);
            }

            throw new Exception("The translation file $file does not exists.");
        }

        public static function segment($lng)
        {
            if (!isset(static::$items[$lng])) {
                static::$items[$lng] = [];
            }
        }

        public static function get($lng, $key, $args = [], $default = null)
        {
            self::segment($lng);

            $val = isAke(static::$items[$lng], $key, $default);

            if (!empty($args)) {
                foreach ($args as $k => $v) {
                    $val = str_replace("##$k##", $v, $val);
                }
            }

            return $val;
        }

        public static function set($lng, $key, $value = null)
        {
            self::segment($lng);
            static::$items[$lng][$key] = $value;
        }

        public static function has($lng, $key)
        {
            return 'octodummy' != static::get($lng, $key, [], 'octodummy');
        }

        public static function copy($lng, $from, $to, $args = [], $d = null)
        {
            self::set($to, self::get($lng, $from, $args, $d));
        }

        public static function replace($lng, $from, $to, $args = [], $d = null)
        {
            self::set($to, self::get($lng, $from, $d, $args));

            return static::forget($from);
        }

        public static function change($lng, $k, $v, $args = [])
        {
            $val = self::get($lng, $k, $args);

            self::set($lng, '__old.' . $k, $val);
            self::set($lng, $k, $v);
        }

        public static function old($lng, $k, $args = [])
        {
            $val = self::get($lng, '__old.' . $k, $args);

            self::set($lng, $k, $val);
        }

        public static function del($lng, $key)
        {
            return static::forget($lng, $key);
        }

        public static function forget($lng, $key)
        {
            if (static::has($key)) {
                unset(static::$items[$lng][$key]);

                return true;
            }

            return false;
        }

        public static function all($lng)
        {
            self::segment($lng);

            return static::$items[$lng];
        }

        public static function __callStatic($m, $a)
        {
            if ('new' == $m) {
                $lng    = array_shift($a);
                $k      = array_shift($a);
                $v      = array_shift($a);
                $args   = array_shift($a);

                if (!$args) $arhs = [];

                return self::change($lng, $k, $v, $args);
            }
        }
    }
