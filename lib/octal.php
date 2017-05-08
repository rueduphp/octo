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

                if (in_array('boot', $methods)) {
                    static::boot($this);
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
         * updated
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

        public static function booting(callable $callable)
        {
            $instance = maker(get_called_class(), [], false);

            return $instance->orm()->on('booting', $callable, $instance);
        }

        public static function booted(callable $callable)
        {
            $instance = maker(get_called_class(), [], false);

            return $instance->orm()->on('booted', $callable, $instance);
        }

        public static function saving(callable $callable)
        {
            $instance = maker(get_called_class(), [], false);

            return $instance->orm()->on('saving', $callable, $instance);
        }

        public static function saved(callable $callable)
        {
            $instance = maker(get_called_class(), [], false);

            return $instance->orm()->on('saved', $callable, $instance);
        }

        public static function creating(callable $callable)
        {
            $instance = maker(get_called_class(), [], false);

            return $instance->orm()->on('creating', $callable, $instance);
        }

        public static function created(callable $callable)
        {
            $instance = maker(get_called_class(), [], false);

            return $instance->orm()->on('created', $callable, $instance);
        }

        public static function updating(callable $callable)
        {
            $instance = maker(get_called_class(), [], false);

            return $instance->orm()->on('updating', $callable, $instance);
        }

        public static function updated(callable $callable)
        {
            $instance = maker(get_called_class(), [], false);

            return $instance->orm()->on('updated', $callable, $instance);
        }

        public static function deleting(callable $callable)
        {
            $instance = maker(get_called_class(), [], false);

            return $instance->orm()->on('deleting', $callable, $instance);
        }

        public static function deleted(callable $callable)
        {
            $instance = maker(get_called_class(), [], false);

            return $instance->orm()->on('deleted', $callable, $instance);
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

                $database   = $orm->db;
                $table      = $orm->table;

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
