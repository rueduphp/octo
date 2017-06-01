<?php
    if (!function_exists('partial')) {
        function partial()
        {
            return call_user_func_array('\\Octo\\partial', func_get_args());
        }
    }

    if (!function_exists('guard')) {
        function guard()
        {
            return call_user_func_array('\\Octo\\guard', func_get_args());
        }
    }

    if (!function_exists('auth')) {
        function auth()
        {
            return call_user_func_array('\\Octo\\guard', func_get_args());
        }
    }

    if (!function_exists('vue')) {
        function vue()
        {
            return call_user_func_array('\\Octo\\vue', func_get_args());
        }
    }

    if (!function_exists('em')) {
        function em()
        {
            return call_user_func_array('\\Octo\\em', func_get_args());
        }
    }

    if (!function_exists('dd')) {
        function dd()
        {
            return call_user_func_array('\\Octo\\dd', func_get_args());
        }
    }

    if (!function_exists('vd')) {
        function vd()
        {
            return call_user_func_array('\\Octo\\vd', func_get_args());
        }
    }

    if (!function_exists('isAke')) {
        function isAke()
        {
            return call_user_func_array('\\Octo\\isAke', func_get_args());
        }
    }

    if (!function_exists('lib')) {
        function lib()
        {
            return call_user_func_array('\\Octo\\lib', func_get_args());
        }
    }

    if (!function_exists('maker')) {
        function maker()
        {
            return call_user_func_array('\\Octo\\maker', func_get_args());
        }
    }

    if (!function_exists('image')) {
        function image()
        {
            return call_user_func_array('\\Octo\\image', func_get_args());
        }
    }

    if (!function_exists('forever')) {
        function forever()
        {
            return call_user_func_array('\\Octo\\forever', func_get_args());
        }
    }

    if (!function_exists('item')) {
        function item()
        {
            return call_user_func_array('\\Octo\\item', func_get_args());
        }
    }

    if (!function_exists('isRoute')) {
        function isRoute()
        {
            return call_user_func_array('\\Octo\\isRoute', func_get_args());
        }
    }

    if (!function_exists('path')) {
        function path()
        {
            return call_user_func_array('\\Octo\\path', func_get_args());
        }
    }

    if (!function_exists('coll')) {
        function coll()
        {
            return call_user_func_array('\\Octo\\coll', func_get_args());
        }
    }

    if (!function_exists('memory')) {
        function memory()
        {
            return call_user_func_array('\\Octo\\dbMemory', func_get_args());
        }
    }

    if (!function_exists('dyn')) {
        function dyn()
        {
            return call_user_func_array('\\Octo\\dyn', func_get_args());
        }
    }

    if (!function_exists('lower')) {
        function lower()
        {
            return call_user_func_array('\\Octo\\lower', func_get_args());
        }
    }

    if (!function_exists('upper')) {
        function upper()
        {
            return call_user_func_array('\\Octo\\upper', func_get_args());
        }
    }

    if (!function_exists('o')) {
        function o()
        {
            return call_user_func_array('\\Octo\\o', func_get_args());
        }
    }

    if (!function_exists('start_session')) {
        function start_session()
        {
            echo call_user_func_array('\\Octo\\start_session', func_get_args());
        }
    }

    if (!function_exists('session')) {
        function session()
        {
            echo call_user_func_array('\\Octo\\session', func_get_args());
        }
    }

    if (!function_exists('_')) {
        function _()
        {
            echo call_user_func_array('\\Octo\\trans', func_get_args());
        }
    }

    if (!function_exists('trans')) {
        function trans()
        {
            return call_user_func_array('\\Octo\\trans', func_get_args());
        }
    }

    if (!function_exists('lng')) {
        function lng()
        {
            return call_user_func_array('\\Octo\\lng', func_get_args());
        }
    }

    if (!function_exists('request')) {
        function request()
        {
            $callable = ['\\Octo\\Input', 'request'];

            return call_user_func_array($callable, func_get_args());
        }
    }

    if (!function_exists('url')) {
        function url()
        {
            return call_user_func_array('\\Octo\\url', func_get_args());
        }
    }

    if (!function_exists('redirect')) {
        function redirect()
        {
            return call_user_func_array('\\Octo\\redirect', func_get_args());
        }
    }

    if (!function_exists('auth')) {
        function auth()
        {
            return call_user_func_array('\\Octo\\auth', func_get_args());
        }
    }

    if (!function_exists('controller')) {
        function controller()
        {
            return call_user_func_array('\\Octo\\controller', func_get_args());
        }
    }

    if (!function_exists('current_url')) {
        function current_url()
        {
            return call_user_func_array('\\Octo\\current_url', func_get_args());
        }
    }

    if (!function_exists('layout')) {
        function layout()
        {
            return call_user_func_array('\\Octo\\layout', func_get_args());
        }
    }

    if (!function_exists('actual')) {
        function actual()
        {
            return call_user_func_array('\\Octo\\actual', func_get_args());
        }
    }

    if (!function_exists('action')) {
        function action($action, $args = [])
        {
            $controller = actual('controller');

            $controller->action = $action;

            $callable = [$controller, $action];

            return call_user_func_array($callable, $args);
        }
    }

    if (!function_exists('forward')) {
        function forward($url, $method = 'GET')
        {
            $url = url('/' . $url);

            $_SERVER['REQUEST_URI']     = $url;
            $_SERVER['REQUEST_METHOD']  = $method;

            lib('router')->run();

            exit;
        }
    }

    if (!function_exists('user')) {
        function user($k = null)
        {
            $user = auth()->user();

            if (!$user) {
                $user = [];
            }

            if ($k) {
                return isAke($user, $k, null);
            }

            return $user;
        }
    }

    if (!function_exists('magic')) {
        function magic()
        {
            return call_user_func_array('\\Octo\\magic', func_get_args());
        }
    }

    if (!function_exists('context')) {
        function context()
        {
            return call_user_func_array('\\Octo\\context', func_get_args());
        }
    }

    if (!function_exists('mockery')) {
        function mockery()
        {
            return call_user_func_array('\\Octo\\mockery', func_get_args());
        }
    }

    if (!function_exists('mailto')) {
        function mailto()
        {
            return call_user_func_array('\\Octo\\mailto', func_get_args());
        }
    }

    if (!function_exists('queue')) {
        function queue()
        {
            return call_user_func_array('\\Octo\\queue', func_get_args());
        }
    }

    if (!function_exists('listenQueue')) {
        function listenQueue()
        {
            return call_user_func_array('\\Octo\\listenQueue', func_get_args());
        }
    }

    if (!function_exists('bgQueue')) {
        function bgQueue()
        {
            return call_user_func_array('\\Octo\\bgQueue', func_get_args());
        }
    }

    if (!function_exists('appenv')) {
        function appenv()
        {
            return call_user_func_array('\\Octo\\appenv', func_get_args());
        }
    }

    if (!class_exists('Core')) {
        class Core
        {
            public static function instance()
            {
                return context('app');
            }

            public static function get()
            {
                return context('app');
            }

            public static function __callStatic($m, $a)
            {
                return call_user_func_array([context('app'), $m], $a);
            }
        }
    }

    if (!class_exists('Octo')) {
        class Octo
        {
            public static function instance()
            {
                return context('app');
            }

            public static function get()
            {
                return context('app');
            }

            public static function __callStatic($m, $a)
            {
                return call_user_func_array([context('app'), $m], $a);
            }
        }
    }

    if (!class_exists('Registry')) {
        class Registry
        {
            public static function __callStatic($m, $a)
            {
                return call_user_func_array([lib('now'), $m], $a);
            }
        }
    }

    if (!class_exists('Strings')) {
        class Strings
        {
            public static function __callStatic($m, $a)
            {
                return call_user_func_array([lib('inflector'), $m], $a);
            }
        }
    }

    if (!class_exists('Dir')) {
        class Dir
        {
            public static function __callStatic($m, $a)
            {
                return call_user_func_array([lib('file'), $m], $a);
            }
        }
    }

    if (!class_exists('Utils')) {
        class Utils
        {
            public static function __callStatic($m, $a)
            {
                if (function_exists('\\Octo\\' . $m)) {
                    return call_user_func_array('\\Octo\\' . $m, $a);
                }

                if (function_exists($m)) {
                    return call_user_func_array($m, $a);
                }

                throw new Exception("The method $m does not exist!");
            }
        }
    }

    if (!class_exists('Helpers')) {
        class Helpers
        {
            public static function __callStatic($m, $a)
            {
                if (function_exists('\\Octo\\' . $m)) {
                    return call_user_func_array('\\Octo\\' . $m, $a);
                }

                if (function_exists($m)) {
                    return call_user_func_array($m, $a);
                }

                throw new Exception("The method $m does not exist!");
            }
        }
    }
