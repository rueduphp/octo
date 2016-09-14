<?php
    namespace Octo;

    Class Input
    {
        public static function get($k, $d = null)
        {
            $all = self::clean($_GET) + self::clean($_POST) + self::clean($_REQUEST);

            return isAke(oclean($all), $k, value($d));
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
    }
