<?php
    if (!function_exists('partial')) {
        function partial()
        {
            return call_user_func_array('\\Octo\\partial', func_get_args());
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

    if (!function_exists('o')) {
        function o()
        {
            return call_user_func_array('\\Octo\\o', func_get_args());
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
