<?php
    namespace Octo;

    class Autoloader
    {
        protected static $aliases = [];

        public function loader($class)
        {
            if (!class_exists($class)) {
                foreach (self::$aliases as $alias => $className) {
                    if ($alias == $class) {
                        return class_alias($className, $alias);
                    }
                }

                $tab    = explode('\\', $class);
                $ns     = array_shift($tab);
                $lib    = array_shift($tab);

                if ('Octo' == $ns) {
                    if (!empty($tab)) {
                        $file = __DIR__ . DS . strtolower($lib) . DS . implode(DS, $tab) . '.php';
                    } else {
                        $file = __DIR__ . DS . strtolower($lib) . '.php';
                    }

                    if (file_exists($file)) {
                        require_once $file;

                        return;
                    }
                } else {
                    if ($ns == $class || empty($ns)) {
                        if (class_exists('\\' . __NAMESPACE__ . '\\' . $class)) {
                            return class_alias('\\' . __NAMESPACE__ . '\\' . $class, $class);
                        }

                        if (!empty($tab)) {
                            $file = __DIR__ . DS . strtolower($lib) . DS . implode(DS, $tab) . '.php';
                        } else {
                            $file = __DIR__ . DS . strtolower($lib) . '.php';
                        }

                        if (file_exists($file)) {
                            require_once $file;

                            return class_alias('\\' . __NAMESPACE__ . '\\' . $class, $class);
                        }
                    }
                }

                if (!defined('OCTO_STANDALONE')) {
                    aliases($class);
                }
            }
        }

        public static function alias($alias, $class)
        {
            static::$aliases[$alias] = $class;
        }
    }
