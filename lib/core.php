<?php
    namespace Octo;

    use Reflectionclass;

    class Core
    {
        use Notifiable;

        public static function instance()
        {
            if (!Registry::has('singletons.' . $class = get_called_class())) {
                $ref    = new Reflectionclass($class);
                $args   = func_get_args();

                Registry::set(
                    $class,
                    $args ? $ref->newinstanceargs($args) : new $class
                );
            }

            return Registry::get('singletons.' . $class);
        }

        public static function time()
        {
            $tab    = explode('.', microtime(true));
            $sec    = array_shift($tab);
            $usec   = array_shift($tab);

            if (strlen($usec) == 3) $usec .= '0';

            return $sec . $usec;
        }

        public static function __callStatic($m, $a)
        {
            $octoM = '\\Octo\\' . $m;

            if (function_exists($octoM)) {
                return call_user_func_array($octoM, $a);
            }

            if (function_exists(Strings::lower($octoM))) {
                return call_user_func_array(Strings::lower($octoM), $a);
            }

            if (function_exists($m)) {
                return call_user_func_array($m, $a);
            }

            if (function_exists(Strings::lower($m))) {
                return call_user_func_array(Strings::lower($m), $a);
            }

            $unmerge = array_shift($a);

            if (!$unmerge) {
                $data = array_merge(
                    Input::clean($GLOBALS),
                    array_merge(
                        Input::clean($_GET),
                        Input::clean($_POST)
                    )
                );
            } else {
                $data = $GLOBALS;
            }

            $value = isAke($data, Strings::uncamelize($m), 'octodummy');

            if ('octodummy' != $value) {
                return $value;
            } else {
                throw new Exception("Method $m does not exist!");
            }
        }
    }
