<?php
    namespace Octo;

    class Octal
    {
        protected $entity;
        protected $entityFields = [];
        protected static $booted = [];

        public function __construct()
        {
            $class      = get_called_class();
            $methods    = get_class_methods($this);

            if (!isset(self::$booted[$class])) {
                self::$booted[$class] = true;

                if (in_array('events', $methods)) {
                    static::events($this);
                }

                $this->fire('booting');

                $traits     = class_uses($class);

                if (!empty($traits)) {
                    foreach ($traits as $trait) {
                        $tab        = explode('\\', $trait);
                        $traitName  = Inflector::lower(end($tab));
                        $method     = lcfirst(Inflector::camelize('boot_' . $traitName . '_trait'));

                        if (in_array($method, $methods)) {
                            call_user_func_array([$this, $method], []);
                        }
                    }
                }

                $em         = em($this->entity);
                $database   = $em->db;
                $table      = $em->table;

                actual("entity.$database.$table", $this);

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
        public function on($event, callable $callable)
        {
            return $this->orm()->on($event, $callable, $this);
        }

        public function hook(callable $callable)
        {
            return call_user_func_array($callable, [$this->orm()]);
        }

        public static function __callStatic($m, $a)
        {
            $instance = maker(get_called_class(), [], false);

            if ('new' == $m) {
                return static::store(current($a));
            } elseif ('find' == $m) {
                $orm = $instance->orm();
                $row = $res = call_user_func_array([$orm, 'find'], $a);

                if (is_array($row)) {
                    $row = $orm->model($row);
                }

                $database = $orm->db;
                $table = $orm->table;

                actual("row.$database.$table", $row);

                return $res;
            }

            return call_user_func_array([$instance, $m], $a);
        }

        public function __call($m, $a)
        {
            if ('new' == $m) {
                return $this->store(current($a));
            }

            return call_user_func_array([$this->orm(), $m], $a);
        }

        public function __toString()
        {
            $orm = $this->orm();

            $database = $orm->db;
            $table = $orm->table;

            if ($row = actual("row.$database.$table")) {
                return $row->toJson();
            }
        }

        public function __invoke($id)
        {
            if (is_numeric($id)) {
                return $this->orm()->find($id);
            }
        }
    }
