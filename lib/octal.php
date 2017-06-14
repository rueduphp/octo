<?php
    namespace Octo;

    class Octal
    {
        protected $entity;
        protected $__instance;
        protected $entityFields = [];
        protected static $booted = [];

        public function __construct($newRow = null)
        {
            $class = get_called_class();

            if (!isset($this->entity) && fnmatch('*Entity', $class)) {
                $this->entity = Strings::uncamelize(
                    str_replace(
                        'Entity',
                        '',
                        Arrays::last(
                            explode(
                                '\\',
                                $class
                            )
                        )
                    )
                );
            }

            $em         = em($this->entity);
            $database   = $em->db;
            $table      = $em->table;

            $this->__instance = hash(uuid() . "entity.$database.$table");

            if (is_array($newRow)) {
                $this->row = $em->store($newRow);
            }

            $methods = get_class_methods($this);

            if (!isset(static::$booted[$class])) {
                static::$booted[$class] = true;

                if (in_array('events', $methods)) {
                    static::events($this);
                }

                if (in_array('policies', $methods)) {
                    static::policies($this);
                }

                if (in_array('boot', $methods)) {
                    static::boot($this);
                }

                $this->fire('booting');

                $traits = class_uses($class);

                if (!empty($traits)) {
                    foreach ($traits as $trait) {
                        $tab        = explode('\\', $trait);
                        $traitName  = Strings::lower(end($tab));
                        $method     = lcfirst(Strings::camelize('boot_' . $traitName . '_trait'));

                        if (in_array($method, $methods)) {
                            call_user_func_array([$this, $method], []);
                        }
                    }
                }

                actual("entity.$database.$table", $this);

                $this->fire('booted');
            }
        }

        public function __set($key, $value)
        {
            if ('row' == $key) {
                Registry::set('row.' . $this->__instance, $value);
            } else {
                $this->$key = $value;
            }
        }

        public function __get($key)
        {
            if ('row' == $key) {
                return Registry::get('row.' . $this->__instance);
            } else {
                if (isset($this->$key)) {
                    return $this->$key;
                }
            }

            return null;
        }

        public function __isset($key)
        {
            if ('row' == $key) {
                return 'octodummy' != Registry::get('row.' . $this->__instance, 'octodummy');
            }

            return isset($this->$key);
        }

        public function __unset($key)
        {
            if ('row' == $key) {
                Registry::delete('row.' . $this->__instance);
            } else {
                unset($this->$key);
            }
        }

        public function orm()
        {
            return em($this->entity)->octal($this);
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
         * make_model
         */
        public function on($event, callable $callable)
        {
            return $this->orm()->on($event, $callable, $this);
        }

        public function hook(callable $callable)
        {
            return call_user_func_array($callable, [$this->orm()]);
        }

        public static function makeModel(callable $callable)
        {
            $instance = maker(get_called_class(), [], false);

            return $instance->orm()->on('make_model', $callable, $instance);
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
            } elseif ('oldest' == $m) {
                    return static::sortBy('id');
            } elseif ('newest' == $m) {
                return static::sortByDesc('id');
            }

            return call_user_func_array([$instance, $m], $a);
        }

        public function __call($m, $a)
        {
            if (isset($this->row)) {
                return call_user_func_array([$this->row, $m], $a);
            } else {
                if ('newest' == $m) {
                    return $this->sortByDesc('id');
                } elseif ('oldest' == $m) {
                    return $this->sortBy('id');
                } elseif ('new' == $m) {
                    return $this->store(current($a));
                }

                return call_user_func_array([$this->orm(), $m], $a);
            }
        }

        public function __toString()
        {
            if (isset($this->row)) {
                return $this->row->toJson();
            } else {
                $orm        = $this->orm();
                $database   = $orm->db;
                $table      = $orm->table;

                return "$database.$table";
            }
        }

        public function __invoke($concern = null)
        {
            if (is_numeric($concern)) {
                return $this->orm()->find((int) $concern);
            }

            if (is_array($concern)) {
                return $this->orm()->store((array) $concern);
            }

            return $this;
        }
    }
