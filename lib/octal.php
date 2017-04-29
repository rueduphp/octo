<?php
    namespace Octo;

    class Octal
    {
        protected $app;
        protected $entity;
        protected $entityFields = [];

        public function __construct(App $app)
        {
            $this->app = $app;
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

        public static function __callStatic($m, $a)
        {
            $instance = maker(get_called_class(), [], false);

            return call_user_func_array([$instance, $m], $a);
        }

        public function __call($m, $a)
        {
            return call_user_func_array([$this->orm(), $m], $a);
        }
    }
