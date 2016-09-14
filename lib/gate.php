<?php
    namespace Octo;

    class Gate
    {
        public static function __callStatic($method, $args)
        {
            $seg = Inflector::uncamelize($method);

            if (fnmatch('*_*', $seg)) {
                list($ns, $method) = explode('_', $seg, 2);

                return call_user_func_array([lib('guard', [$ns]), $method], $args);
            }
        }
    }
