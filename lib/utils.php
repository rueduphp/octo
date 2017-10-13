<?php
    namespace Octo;

    class Utils
    {
        public function __call($m, $a)
        {
            if (!empty($a)) {
                return lib($m, $a);
            }

            return lib($m);
        }

        public static function __callStatic($m, $a)
        {
            if (!empty($a)) {
                return lib($m, $a);
            }

            return lib($m);
        }

        public static function getScheme()
        {
            $protocol = 'http://';

            if (isset($_SERVER['HTTPS']) && in_array($_SERVER['HTTPS'], ['on', 1])) {
                $protocol = 'https://';
            } else if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
                $protocol = 'https://';
            } else if (stripos($_SERVER['SERVER_PROTOCOL'], 'https') === true) {
                $protocol = 'https://';
            }

            return $protocol;
        }

        public static function removeByKey()
        {
            $keys   = func_get_args();
            $array  = array_shift($keys);

            foreach ($array as $k => $v) {
                if (in_array($k, $keys)) {
                    unset($array[$k]);
                }
            }

            return $array;
        }

        public static function removeByValue()
        {
            $values = func_get_args();
            $array  = array_shift($values);

            foreach ($array as $k => $v) {
                if (in_array($v, $values)) {
                    unset($array[$k]);
                }
            }

            return $array;
        }

        public static function xcopy($source, $dest, $permissions = 0755)
        {
            if (is_link($source)) {
                return symlink(readlink($source), $dest);
            }

            if (is_file($source)) {
                return copy($source, $dest);
            }

            if (!is_dir($dest)) {
                mkdir($dest, $permissions);
            }

            $dir = dir($source);

            while (false !== $entry = $dir->read()) {
                if ($entry == '.' || $entry == '..') {
                    continue;
                }

                self::xcopy("$source/$entry", "$dest/$entry", $permissions);
            }

            $dir->close();

            return true;
        }

        public static function xdelete($dir)
        {
            if (is_dir($dir)) {
                $objects = scandir($dir);

                foreach ($objects as $object) {
                    if ($object != '.' && $object != '..') {
                        if (is_dir($dir . '/' . $object)) {
                            self::xdelete($dir . '/' . $object);
                        } else {
                            unlink($dir . '/' . $object);
                        }
                    }
                }

                rmdir($dir);
            }

            return true;
        }

        public static function isSerialized($data, $strict = true)
        {
            if (!is_string($data)) {
                return false;
            }

            $data = trim($data);

            if ('N;' == $data) {
                return true;
            }

            if (strlen($data) < 4) {
                return false;
            }

            if (':' !== $data[1]) {
                return false;
            }

            if ($strict) {
                $lastc = substr($data, -1);

                if (';' !== $lastc && '}' !== $lastc) {
                    return false;
                }
            } else {
                $semicolon = strpos($data, ';');
                $brace     = strpos($data, '}');

                if (false === $semicolon && false === $brace) return false;
                if (false !== $semicolon && $semicolon < 3) return false;
                if (false !== $brace && $brace < 4) return false;
            }

            $token = $data[0];

            switch ($token) {
                case 's' :
                    if ($strict) {
                        if ('"' !== substr($data, -2, 1)) {
                            return false;
                        }
                    } elseif (false === strpos($data, '"')) {
                        return false;
                    }
                case 'a' :
                case 'O' :
                    return (bool) preg_match("/^{$token}:[0-9]+:/s", $data);
                case 'b' :
                case 'i' :
                case 'd' :
                    $end = $strict ? '$' : '';

                    return (bool) preg_match("/^{$token}:[0-9.E-]+;$end/", $data);
            }

            return false;
        }

        function doSerialize($data)
        {
            if (is_array($data) || is_object($data)) return serialize($data);

            if (static::isSerialized($data, false)) return serialize( $data );

            return $data;
        }

        public static function doUnserialize($value)
        {
            if (static::isSerialized($value)) return @unserialize($value);

            return $value;
        }
    }
