<?php
    namespace Octo;

    class Autoloader
    {
        protected static $aliases = [];
        protected static $mapped = [];

        public function loader($class)
        {
            if (!class_exists($class)) {
                foreach (self::$aliases as $alias => $className) {
                    if ($alias === $class) {
                        return class_alias($className, $alias);
                    }
                }

                if ($file = $this->find($class)) {
                    require_once $file;

                    return;
                }

                $tab    = explode('\\', $class);
                $ns     = array_shift($tab);
                $lib    = array_shift($tab);

                if ('Octo' === $ns) {
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
                    if ($ns === $class || empty($ns)) {
                        if (class_exists($facade = 'Octo\\Facades\\' . $class)) {
                            return class_alias($facade, $class);
                        } elseif (class_exists($target = Setup::alias($class))) {
                            eval($this->getFacade($class, $target));

                            return;
                        }

                        if (class_exists('\\' . __NAMESPACE__ . '\\' . $class)) {
                            return class_alias('\\' . __NAMESPACE__ . '\\' . $class, $class);
                        }
//
                        if (!empty($tab)) {
                            $file = __DIR__ . DS . strtolower($lib) . DS . implode(DS, $tab) . '.php';
                        } else {
                            $file = __DIR__ . DS . strtolower($lib) . '.php';
                        }

                        if (file_exists($file)) {
                            require_once $file;

                            return class_alias('\\' . __NAMESPACE__ . '\\' . $class, $class);
                        }
                    } else {
                        if (class_exists($target = Setup::alias($class))) {
                            eval($this->getFacade($class, $target));

                            return;
                        }
                    }
                }
            }
        }

        private function getFacade($class, $target)
        {
            $to = deNamespace($class);
            $namespace = getNamespace($class);

            $facade = '';

            if (!empty($namespace)) {
                $facade .= 'namespace ' . $namespace . '; ';
            }

            $facade .= 'class ' . $to . ' extends \Octo\Facade {
            
            public static function getNativeClass()
            {
                return "'.$target.'";
            }
            }';

            return $facade;
        }

        private function find($class)
        {
            return isset(self::$mapped[$class]) ? self::$mapped[$class] : null;
        }

        public static function entity($class)
        {
            $tab = explode(
                '\\',
                $class
            );

            $entity = str_replace(
                'Entity',
                '',
                end($tab)
            );

            $file = path('app') . '/entities/' . $entity . '.php';

            static::map($class, $file);
        }

        public static function map($class, $file)
        {
            if (file_exists($file)) {
                static::$mapped[$class] = $file;
            }
        }

        public static function alias($alias, $class)
        {
            static::$aliases[$alias] = $class;
        }

        public static function mapping(array $maps)
        {
            foreach ($maps as $class => $file) {
                static::map($class, $file);
            }
        }

        public static function aliasing(array $aliases)
        {
            foreach ($aliases as $alias => $class) {
                static::alias($alias, $class);
            }
        }
    }
