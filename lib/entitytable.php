<?php
    namespace Octo;

    class EntityTable
    {
        public static function __callStatic($method, $args)
        {
            $i = new Database(Strings::lower(def('SITE_NAME', 'core')));

            return call_user_func_array([$i, $method], $args);
        }
    }
