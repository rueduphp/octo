<?php
    namespace Octo;

    class Request
    {
        use Macroable;

        public static function post($key = null, $default = null)
        {
            if (!$key) {
                return classify('postRequest', $_POST);
            }

            return isAke($_POST, $key, $default);
        }

        public static function get($key = null, $default = null)
        {
            if (!$key) {
                return classify('getRequest', $_GET);
            }

            return isAke($_GET, $key, $default);
        }

        public static function files($key = null, $default = null)
        {
            if (!$key) {
                return classify('filesRequest', $_FILES);
            }

            return isAke($_FILES, $key, $default);
        }

        public static function cookies($key = null, $default = null)
        {
            if (!$key) {
                return classify('cookiesRequest', $_COOKIE);
            }

            return isAke($_COOKIE, $key, $default);
        }

        public static function session($key = null, $default = null)
        {
            if (!$key) {
                return classify('sessionRequest', $_SESSION);
            }

            return isAke($_SESSION, $key, $default);
        }

        public static function server($key = null, $default = null)
        {
            if (!$key) {
                return classify('serverRequest', $_SERVER);
            }

            return isAke($_SERVER, $key, $default);
        }

        public static function has($key)
        {
            return 'octodummy' != isAke($_REQUEST, $key, 'octodummy');
        }

        public static function exists($key)
        {
            return 'octodummy' != isAke($_REQUEST, $key, 'octodummy');
        }

        public static function only($keys)
        {
            $keys = is_array($keys) ? $keys : func_get_args();

            $results = [];

            foreach ($keys as $key) {
                aset($results, $key, isAke($_REQUEST, $key, null));
            }

            return $results;
        }

        public static function except($keys)
        {
            $keys = is_array($keys) ? $keys : func_get_args();

            $results = $_REQUEST;

            adel($results, $keys);

            return $results;
        }

        public static function all()
        {
            return $_REQUEST + $_COOKIE;
        }

        public static function hasCookie($key)
        {
            return !is_null(static::cookies($key));
        }

        public static function headers()
        {
            if (function_exists('getallheaders')) {
                return getallheaders();
            }

            $headers = [];

            foreach ($_SERVER as $name => $value) {
                if (
                    (substr($name, 0, 5) === 'HTTP_')
                    || ($name === 'CONTENT_TYPE')
                    || ($name === 'CONTENT_LENGTH')
                ) {
                    $headers[str_replace(
                        [' ', 'Http'],
                        ['-', 'HTTP'],
                        ucwords(
                            strtolower(
                                str_replace(
                                    '_',
                                    ' ',
                                    substr(
                                        $name,
                                        5
                                    )
                                )
                            )
                        )
                    )] = $value;
                }
            }

            return $headers;
        }

        public static function method()
        {
            $method = $_SERVER['REQUEST_METHOD'];

            if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
                ob_start();
                $method = 'GET';
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $headers = self::headers();

                if (isset($headers['X-HTTP-Method-Override']) && in_array($headers['X-HTTP-Method-Override'], ['PUT', 'DELETE', 'PATCH'])) {
                    $method = isAke($headers, 'X-HTTP-Method-Override', 'PUT');
                }

                if (isset($headers['_method']) && in_array($headers['_method'], ['PUT', 'DELETE', 'PATCH'])) {
                    $method = isAke($headers, '_method', 'PUT');
                }
            }

            return $method;
        }

        public static function url($baseRoute = '')
        {
            $protocol = 'http';

            if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
                $protocol .= 's';
            }

            if ($_SERVER["SERVER_PORT"] != "80") {
                return "$protocol://" .
                $_SERVER["SERVER_NAME"] .
                ':' .
                $_SERVER["SERVER_PORT"] .
                self::uri($baseRoute);
            } else {
                return "$protocol://" .
                $_SERVER["SERVER_NAME"] .
                self::uri($baseRoute);
            }
        }

        public static function path($baseRoute = '')
        {
            return static::uri($baseRoute);
        }

        public static function uri($baseRoute = '')
        {
            $uri = substr($_SERVER['REQUEST_URI'], strlen($baseRoute));

            if (strstr($uri, '?')) {
                $parts  = preg_split('/\?/', $uri, -1, PREG_SPLIT_NO_EMPTY);
                $uri    = array_shift($parts);
                $qs     = array_shift($parts);

                parse_str($qs, $output);

                foreach ($output as $k => $v) {
                    $_REQUEST[$k] = $v;
                }
            }

            $uri = trim($uri, '/');

            return !strlen($uri) ? '/' : $uri;
        }

        public static function decodedUri()
        {
            return rawurldecode(self::uri());
        }

        public static function ip()
        {
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                return $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
                return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                return $_SERVER['HTTP_X_FORWARDED_FOR'];
            } elseif (!empty($_SERVER['X_FORWARDED_FOR'])) {
                return $_SERVER['X_FORWARDED_FOR'];
            } else {
                return $_SERVER['REMOTE_ADDR'];
            }
        }

        public static function language()
        {
            return \Locale::acceptFromHttp(
                isAke(
                    $_SERVER,
                    "HTTP_ACCEPT_LANGUAGE",
                    Config::get(
                        'app.language',
                        def(
                            'app.language',
                            appenv('DEFAULT_LANGUAGE', 'en')
                        )
                    )
                )
            );
        }

        public static function upload($field)
        {
            return upload($field);
        }
    }
