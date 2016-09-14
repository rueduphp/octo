<?php
    namespace Octo;

    class Ip
    {
        public static function get($default_ip = '127.0.0.1')
        {
            $ip = '';

            foreach (array(
                 'HTTP_CLIENT_IP',
                 'HTTP_X_FORWARDED_FOR',
                 'HTTP_X_FORWARDED',
                 'HTTP_X_CLUSTER_CLIENT_IP',
                 'HTTP_FORWARDED_FOR',
                 'HTTP_FORWARDED',
                 'REMOTE_ADDR')
            as $key) {
                if (array_key_exists($key, $_SERVER) === true) {
                    foreach (explode(',', $_SERVER[$key]) as $item) {
                        if (self::isValid($item) && substr($item, 0, 4) != '127.' && $item != '::1' && $item != '' && !in_array($item, array('255.255.255.0', '255.255.255.255'))) {
                            $ip = $item;

                            break;
                        }
                    }
                }
            }

            return ($ip) ? $ip : $default_ip;
        }

        public static function version($ip = null)
        {
            $ip = is_null($ip) ? self::get() : $ip;

            return strpos($ip, ":") === false ? 4 : 6;
        }

        public static function isValid($ip = null)
        {
            $ip = is_null($ip) ? self::get() : $ip;

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return true;
            } else {
                return false;
            }
        }

        public static function isValidIPv4($ip = null)
        {
            $ip = is_null($ip) ? self::get() : $ip;

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return true;
            } else {
                return false;
            }
        }

        public static function isValidIPv4RegEx($ip = null)
        {
            $ip = is_null($ip) ? self::get() : $ip;

            return preg_match('/^(([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]).){3}([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/', $ip);
        }

        public static function isValidIPv4NoPriv($ip = null)
        {
            $ip = is_null($ip) ? self::get() : $ip;

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE)) {
                return true;
            } else {
                return false;
            }
        }

        public static function isValidIPv6($ip = null)
        {
            $ip = is_null($ip) ? self::get() : $ip;

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return true;
            } else {
                return false;
            }
        }

        public static function isValidIPv6NoPriv($ip = null)
        {
            $ip = is_null($ip) ? self::get() : $ip;

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE)) {
                return true;
            } else {
                return false;
            }
        }

        public static function toNum($ip = null)
        {
            $ip = is_null($ip) ? self::get() : $ip;

            if (trim($ip) == '') {
                return 0;
            } else {
                $tmp = preg_split("#\.#", $ip);

                return ($tmp[3] + $tmp[2] * 256 + $tmp[1] * 256 * 256 + $tmp[0] * 256 * 256 * 256);
            }
        }
    }
