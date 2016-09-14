<?php
    namespace Octo;

    class Check
    {
        public static $bag      = null;
        public static $get      = [];
        public static $post     = [];
        public static $cookie   = [];
        public static $files    = [];
        public static $server   = [];
        public static $session  = [];
        public static $errors   = [];

        public static function init()
        {
            $_GET       = Input::clean($_GET);
            $_POST      = Input::clean($_POST);
            $_COOKIE    = Input::clean($_COOKIE);
            $_FILES     = Input::clean($_FILES);
            $_SERVER    = Input::clean($_SERVER);
            $_SESSION   = Input::clean($_SESSION);

            static::$get        = $_GET;
            static::$post       = $_POST;
            static::$cookie     = $_COOKIE;
            static::$files      = $_FILES;
            static::$server     = $_SERVER;
            static::$session    = $_SESSION;
        }

        public static function fresh()
        {
            static::$errors = [];
        }

        public static function required($field, $message = '##field## is required', $type = null)
        {
            $type = empty($type) ? static::$nag : $type;

            $check = strlen(isAke(static::$$type, $field, null)) > 0;

            if (!$check) {
                static::$errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public static function min($field, $min, $message = '##field## is too short', $type = null)
        {
            $type = empty($type) ? static::$nag : $type;

            $check = strlen(isAke(static::$$type, $field, null)) < $min + 0;

            if (!$check) {
                static::$errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public static function max($field, $max, $message = '##field## is too long', $type = null)
        {
            $type = empty($type) ? static::$nag : $type;

            $check = strlen(isAke(static::$$type, $field, null)) > $max + 0;

            if (!$check) {
                static::$errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public static function fnmatch($field, $fnmatch, $message = '##field## is incorrect', $type = null)
        {
            $type = empty($type) ? static::$nag : $type;

            $check = fnmatch($fnmatch, isAke(static::$$type, $field, null));

            if (!$check) {
                static::$errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public static function match($field, $pattern, $message = '##field## is incorrect', $type = null)
        {
            $type = empty($type) ? static::$nag : $type;

            $check = false;

            if (preg_match_all('#^' . trim($pattern) . '$#', isAke(static::$$type, $field, null), $matches, PREG_OFFSET_CAPTURE)) {
                $check = true;
            }

            if (!$check) {
                static::$errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public static function int($field, $message = '##field## is not an integer', $type = null)
        {
            $type = empty($type) ? static::$nag : $type;

            $check = reallyInt(isAke(static::$$type, $field, null));

            if (!$check) {
                static::$errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public static function float($field, $message = '##field## is not a float', $type = null)
        {
            $type = empty($type) ? static::$nag : $type;

            $check = isAke(static::$$type, $field, null) === floatval(isAke(static::$$type, $field, null));

            if (!$check) {
                static::$errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public static function number($field, $message = '##field## is not a number', $type = null)
        {
            $type = empty($type) ? static::$nag : $type;

            $check = isAke(static::$$type, $field, null) === isAke(static::$$type, $field, null) + 0;

            if (!$check) {
                static::$errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public static function numeric($field, $message = '##field## is not numeric', $type = null)
        {
            $type = empty($type) ? static::$nag : $type;

            $check = is_numeric(isAke(static::$$type, $field, null));

            if (!$check) {
                static::$errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public static function email($field, $message = '##field## is not an email', $type = null)
        {
            $type = empty($type) ? static::$nag : $type;

            $check = filter_var(isAke(static::$$type, $field, null), FILTER_VALIDATE_EMAIL);

            if (!$check) {
                static::$errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public static function bool($field, $message = '##field## is not a boolean', $type = null)
        {
            $type = empty($type) ? static::$nag : $type;

            $check = isAke(static::$$type, $field, null) === (bool) isAke(static::$$type, $field, null);

            if (!$check) {
                static::$errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public static function custom($field, callable $custom, $message = '##field## is incorrect', $type = null)
        {
            $type = empty($type) ? static::$nag : $type;

            $check = call($custom, [isAke(static::$$type, $field, null)]);

            if (!$check) {
                static::$errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public static function success()
        {
            return empty(static::$errors);
        }

        public static function fail()
        {
            return !static::success();
        }

        public static function bag($type)
        {
            static::init();
            static::$bag = $type;
        }

        public static function __callStatic($m, $a)
        {
            $type       = static::$nag;
            $field      = array_shift($a);
            $value      = isAke(static::$$type, $field, null);

            $args = array_merge([$value], $a);

            try {
                call_user_func_array(['Octo\\Assert', $m], $args);
            } catch (\Exception $e) {
                static::$errors[] = str_replace('##field##', $field, $e->getMessage());
            }
        }
    }
