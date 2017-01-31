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
    }
