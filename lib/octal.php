<?php
    namespace Octo;

    /**
     * @method Entity getEntity()
     * @method static all()
     * @method static get()
     * @method static int count()
     * @method static Activerecord find(int $id)
     * @method static min(string $field)
     * @method static max(string $field)
     * @method static avg(string $field)
     * @method static Activerecord|null first()
     * @method static Activerecord|null last()
     * @method static Octalia where($concern, $op = null, $value = null)
     */
    class Octal implements FastModelInterface
    {
        protected $entity;
        protected $__instance;
        protected $entityFields = [];
        protected static $booted = [];

        /**
         * @throws \ReflectionException
         */
        public function __construct()
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

                if (in_array('boot', $methods)) {
                    instanciator()->call($this, 'boot');
                }

                if (in_array('events', $methods)) {
                    instanciator()->call($this, 'events');
                }

                if (in_array('policies', $methods)) {
                    instanciator()->call($this, 'policies');
                }

                if (in_array('rules', $methods)) {
                    instanciator()->call($this, 'rules');
                }

                $this->fire('booting');

                $traits = allClasses($class);

                if (!empty($traits)) {
                    foreach ($traits as $trait) {
                        $tab        = explode('\\', $trait);
                        $traitName  = Strings::lower(end($tab));
                        $method     = lcfirst(Strings::camelize('boot_' . $traitName . '_trait'));

                        if (in_array($method, $methods)) {
                            instanciator()->call($this, $method);
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
            if ('row' === $key) {
                Registry::set('row.' . $this->__instance, $value);
            } else {
                $this->{$key} = $value;
            }
        }

        public function __get($key)
        {
            if ('row' === $key) {
                return Registry::get('row.' . $this->__instance);
            } else {
                if (isset($this->{$key})) {
                    return $this->{$key};
                }
            }

            return null;
        }

        public function __isset($key)
        {
            if ('row' === $key) {
                return 'octodummy' != Registry::get('row.' . $this->__instance, 'octodummy');
            }

            return isset($this->{$key});
        }

        public function __unset($key)
        {
            if ('row' === $key) {
                Registry::delete('row.' . $this->__instance);
            } else {
                unset($this->{$key});
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
            return call_user_func_array([static::called(), $m], $a);
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
                } elseif ('new' === $m || 'create' === $m) {
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
                return $this->orm()->findMany((array) $concern);
            }

            return $this;
        }

        /**
         * @return FastFactory
         */
        public static function factory()
        {
            return new FastFactory(get_called_class(), self::called());
        }
    }
