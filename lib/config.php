<?php
    namespace Octo;

    class Config
    {
        public static $items = [];

        public static function get($key, $default = null)
        {
            return isAke(self::$items, $key, value($default));
        }

        public static function reset(array $new = [])
        {
            self::$items = [];

            foreach ($new as $k => $v) {
                self::set($k, $v);
            }
        }

        public static function fill(array $new = [])
        {
            foreach ($new as $k => $v) {
                self::set($k, $v);
            }
        }

        public static function custom($key, $args = [], $default = null)
        {
            $val = isAke(self::$items, $key, value($default));

            foreach ($args as $k => $v) {
                $val = str_replace("##$k##", $v, $val);
            }

            return $val;
        }

        public static function set($key, $value = null)
        {
            self::$items[$key] = value($value);
        }

        public static function has($key)
        {
            return 'octodummy' != self::get($key, 'octodummy');
        }

        public static function copy($from, $to, $d = null)
        {
            self::set($to, self::get($from, $d));
        }

        public static function replace($from, $to, $d = null)
        {
            self::set($to, self::get($from, $d));

            return self::forget($from);
        }

        public static function change($k, $v)
        {
            self::set('__old.' . $k, self::get($k));
            self::set($k, $v);
        }

        public static function old($k)
        {
            self::set($k, self::get('__old.' . $k));
        }

        public static function del($key)
        {
            return self::forget($key);
        }

        public static function forget($key)
        {
            if (self::has($key)) {
                unset(self::$items[$key]);

                return true;
            }

            return false;
        }

        public static function all()
        {
            return self::$items;
        }

        public static function load($datas)
        {
            if (!is_array($datas)) {
                $datas = include $datas;
            }

            foreach ($datas as $k => $v) {
                self::set($k, $v);
            }
        }

        public static function __callStatic($m, $a)
        {
            if ('new' == $m) {
                return self::change(current($a), end($a));
            }
        }

        public static function toArray()
        {
            $keys = array_keys(self::$items);

            $collection = [];

            foreach ($keys as $key) {
                Arrays::set($collection, $key, self::$items[$key]);
            }

            return $collection;
        }

        public static function toCollection()
        {
            return coll(self::toArray());
        }

        public static function toJson()
        {
            return json_encode(self::toArray());
        }

        public static function toSerialize()
        {
            return serialize(self::toArray());
        }
    }
