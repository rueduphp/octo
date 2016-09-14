<?php
    namespace Octo;

    class Loading
    {
        private static $relevants = [];

        public static function add($namespace, $dir)
        {
            static::$relevants[$namespace] = $dir;
        }

        public static function run($class)
        {
            if (!class_exists($class)) {
                foreach (static::$relevants as $ns => $dir) {
                    if (fnmatch("$ns\\*", $class)) {
                        if (!is_callable($dir)) {
                            $tab    = explode('\\', $class);
                            $ns     = array_shift($tab);
                            $lib    = implode('\\', $tab);
                            $file   = str_replace('\\', '/', $lib);

                            $tab    = explode('/', $file);

                            $last   = array_pop($tab);

                            $file  = implode('/', $tab);

                            if (strlen($file)) {
                                $file .= '/';
                            }

                            $file .= Strings::uncamelize($last) . '.php';

                            if (file_exists($dir . DS  . $file)) {
                                require_once($dir . DS  . $file);
                            }
                        } else {
                            $dir($class);
                        }
                    }
                }
            }
        }

        public static function register()
        {
            return spl_autoload_register(array ('Octo\\Loading', 'run'));
        }
    }
