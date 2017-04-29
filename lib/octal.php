<?php
    namespace Octo;

    class Octal
    {
        protected $entity;
        protected $entityFields = [];
        protected static $booted = [];

        public function __construct()
        {
            $class = get_called_class();

            if (!isset(self::$booted[$class])) {
                self::$booted[$class] = true;

                $this->fire('booting');

                $traits = class_uses($class);

                if (!empty($traits)) {
                    foreach ($traits as $trait) {
                        $tab        = explode('\\', $trait);
                        $traitName  = Inflector::lower(end($tab));
                        $method     = lcfirst(Inflector::camelize('boot_' . $traitName . '_trait'));
                        $methods    = get_class_methods($this);

                        if (in_array($method, $methods)) {
                            call_user_func_array([$this, $method], []);
                        }
                    }
                }

                $this->fire('booted');
            }
        }

        public function orm()
        {
            return em($this->entity)->newQuery();
        }

        public function setEntityField($field)
        {
            if (!in_array($field, $this->entityFields)) {
                $this->entityFields[] = $fields;
            }

            return $this;
        }

        /**
         * booting
         * booted
         * saving
         * saved
         * creating
         * created
         * updating
         * deleting
         * deleted
         * get
         * count
         */
        public function setEntityEvent($event, callable $callable)
        {
            return $this->orm()->on($event, $callable, $this);
        }

        public function hook(callable $callable)
        {
            return call_user_func_array($callable, [$this->orm()]);
        }

        public static function __callStatic($m, $a)
        {
            if ('new' == $m) {
                return static::store(current($a));
            }

            $instance = maker(get_called_class(), [], false);

            return call_user_func_array([$instance, $m], $a);
        }

        public function __call($m, $a)
        {
            return call_user_func_array([$this->orm(), $m], $a);
        }
    }
