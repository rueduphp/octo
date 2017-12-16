<?php
    namespace Octo;

    /**
     * @method static Octal all()
     * @method static Octal get()
     * @method static Octal count()
     * @method static Octal find($id)
     * @method static Octal min($field)
     * @method static Octal max($field)
     * @method static Octal avg($field)
     * @method static Octal first()
     * @method static Octal last()
     * @method static Octal where($concern, $op = null, $value = null)
     */
    class Octal implements FastModelInterface
    {
        protected $entity;
        protected $__instance;
        protected $entityFields = [];
        protected static $booted = [];

        public function __construct($newRow = null)
        {
            $class = get_called_class();

            if (!isset($this->entity)) {
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

            $uncamelized = Strings::uncamelize($this->entity);

            if (fnmatch('*_*', $uncamelized)) {
                list($database, $table) = explode('_', $uncamelized, 2);
            } else {
                $table = $uncamelized;
                $database = Strings::uncamelize(Config::get('application.name', 'core'));
            }

            actual("entity.{$database}.{$table}", $this);

            $this->__instance = hash(uuid() . "entity.$database.$table");

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

                $traits = allClasses($class);

                if (!empty($traits)) {
                    foreach ($traits as $trait) {
                        $tab        = explode('\\', $trait);
                        $traitName  = Strings::lower(end($tab));
                        $method     = lcfirst(Strings::camelize('boot_' . $traitName . '_trait'));

                        if (in_array($method, $methods)) {
                            call_user_func_array([$this, $method], []);
                        }

                        $method = lcfirst(Strings::camelize('boot_' . $traitName));

                        if (in_array($method, $methods)) {
                            forward_static_call([$class, $method]);
                        }
                    }
                }

                $this->fire('booted');
            }
        }

        public static function called()
        {
            $class  = get_called_class();
            $i      = maker($class);

            if (!isset($i->entity)) {
                $i->entity = Strings::uncamelize(
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

            $uncamelized = Strings::uncamelize($i->entity);

            if (fnmatch('*_*', $uncamelized)) {
                list($database, $table) = explode('_', $uncamelized, 2);
            } else {
                $table = $uncamelized;
                $database = Strings::uncamelize(Config::get('application.name', 'core'));
            }

            return actual("entity.{$database}.{$table}", $i);
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

        /**
         * @return Octalia
         */
        public function orm()
        {
            return em($this->entity)->octal($this);
        }

        /**
         * @param $field
         * @return $this
         */
        public function setEntityField($field)
        {
            if (!in_array($field, $this->entityFields)) {
                $this->entityFields[] = $field;
            }

            return $this;
        }

        public function lastUpdated($timestamp = false)
        {
            $row = $this->sortByDesc('updated_at')->first();

            if ($row) {
                return $timestamp ? $row->updated_at->timestamp : $row;
            }

            return $timestamp ? time() : null;
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
         * query
         * fetch
         * make_model
         */

        public static function __callStatic($m, $a)
        {
            $instance = maker(get_called_class(), [], false);

            if ('new' === $m) {
                return static::store(current($a));
            } elseif ('oldest' === $m) {
                return static::sortBy('id');
            } elseif ('newest' === $m) {
                return static::sortByDesc('id');
            }

            return call_user_func_array([$instance, $m], $a);
        }

        public function __call($m, $a)
        {
            if (isset($this->row)) {
                return call_user_func_array([$this->row, $m], $a);
            } else {
                if ('newest' === $m) {
                    return $this->sortByDesc('id');
                } elseif ('oldest' === $m) {
                    return $this->sortBy('id');
                } elseif ('new' === $m) {
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
