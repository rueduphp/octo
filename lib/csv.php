<?php
    namespace Octo;

    class Csv
    {
        public static $separator = ',';

        public static function toArray($str, $separator = ',')
        {
            self::$separator = $separator;

            $str = preg_replace_callback('/([^"]*)("((""|[^"])*)"|$)/s', 'Octo\\Csv::quotes', $str);
            $str = preg_replace('/\n$/', '', $str);

            return array_map('Octo\\Csv::line', explode("\n", $str));
        }

        public static function quotes($matches)
        {
            $str = str_replace("\r", "\rR", $matches[3]);
            $str = str_replace("\n", "\rN", $str);
            $str = str_replace('""', "\rQ", $str);
            $str = str_replace(',', "\rC", $str);

            return preg_replace('/\r\n?/', "\n", $matches[1]) . $str;
        }

        public static function line($line)
        {
            return array_map('Octo\\Csv::field', explode(self::$separator, $line));
        }

        public static function field($field)
        {
            $field = str_replace("\rC", ',', $field);
            $field = str_replace("\rQ", '"', $field);
            $field = str_replace("\rN", "\n", $field);
            $field = str_replace("\rR", "\r", $field);

            return $field;
        }
    }
