<?php
    namespace Octo;

    class Octal
    {
        private $entity;
        private $entityFields = [];

        public function orm()
        {
            return em($this->entity);
        }

        public function getEntityFields()
        {
            return $this->entityFields;
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
            $orm = em(self::$entity);

            return call_user_func_array([$orm, $m], $a);
        }

        public function __call($m, $a)
        {
            return call_user_func_array([$this->orm(), $m], $a);
        }
    }
