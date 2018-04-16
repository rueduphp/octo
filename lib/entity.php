<?php
    namespace Octo;

    use function get_called_class;
    use PDOException;

    class Entity implements FastModelInterface
    {
        protected $table;
        protected $guarded      = null;
        protected $fillable     = [];
        protected $hidden       = [];
        protected $timestamps   = true;
        protected $softDelete   = false;
        protected $primaryKey   = 'id';

        protected static $booted = [];

        /**
         * @throws \ReflectionException
         */
        public function __construct()
        {
            $class  = get_called_class();

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

        /**
         * @param string $class
         *
         * @return Entity
         *
         * @throws \ReflectionException
         */
        public function observe(string $class): self
        {
            $observers = get('orm.observers', []);
            $self = get_called_class();
            $observers[$self] = maker($class);
            set('orm.observers', $observers);

            return $this;
        }

        public static function clearBooted()
        {
            static::$booted = [];
        }

        /**
         * @param string $event
         * @param null $concern
         * @param bool $return
         *
         * @return mixed|null
         *
         * @throws \ReflectionException
         */
        public function fire(string $event, $concern = null, bool $return = false)
        {
            $methods = get_class_methods($this);
            $method  = 'on' . Strings::camelize($event);

            if (in_array($method, $methods)) {
                $result = $this->{$method}($concern);

                if ($return) {
                    return $result;
                }
            } else {
                $observers = get('orm.observers', []);
                $self = get_called_class();

                $observer = isAke($observers, $self, null);

                if (null !== $observer) {
                    $methods = get_class_methods($observer);

                    if (in_array($event, $methods)) {
                        $result = instanciator()->call($observer, $event);

                        if ($return) {
                            return $result;
                        }
                    }
                }
            }

            return $concern;
        }

        public function setTable($table)
        {
            $this->table = $table;

            return $this;
        }

        /**
         * @return mixed
         *
         * @throws \ReflectionException
         */
        public static function table()
        {
            return static::called()->table;
        }

        /**
         * @return mixed
         * @throws \ReflectionException
         */
        public static function pk()
        {
            return static::called()->primaryKey;
        }

        /**
         * @return mixed
         *
         * @throws \ReflectionException
         */
        public static function guarded()
        {
            return static::called()->guarded;
        }

        /**
         * @return mixed
         *
         * @throws \ReflectionException
         */
        public static function fillable()
        {
            return static::called()->fillable;
        }

        /**
         * @return mixed|null
         *
         * @throws \ReflectionException
         */
        public static function called()
        {
            $class  = get_called_class();
            $i      = instanciator()->factory($class);

            if (!isset($i->table)) {
                $table = $i->table = Strings::lower(
                    Arrays::last(
                        explode(
                            '\\',
                            $class
                        )
                    )
                );
            } else {
                $table = $i->table;
            }

            return actual("orm.entity.$table", $i);
        }

        /**
         * @param array $data
         *
         * @return Record
         *
         * @throws \ReflectionException
         */
        public static function model(array $data = [])
        {
            return new Record($data, static::called());
        }

        /**
         * @return mixed
         * @throws \ReflectionException
         */
        protected static function db()
        {
            return foundry(Orm::class)->table(static::table());
        }

        /**
         * @param null $concern
         * @return $this|string
         * @throws \ReflectionException
         */
        public function __invoke($concern = null)
        {
            if (!empty($concern)) {
                if (is_array($concern)) {
                    return $this->db()->query($concern);
                } else {
                    if (reallyInt($concern)) {
                        return $this->find((int) $concern);
                    }
                }
            } else {
                if ($concern === []) {
                    return $this;
                }
            }

            return get_called_class();
        }

        /**
         * @param array $data
         *
         * @return mixed
         *
         * @throws \ReflectionException
         */
        public static function new(array $data = [])
        {
            return static::create($data);
        }

        /**
         * @param array $data
         * @throws \ReflectionException
         */
        public static function validate(array $data)
        {
            $model = static::model($data);

            $model->clean();
            $model->validate();
        }

        /**
         * @param array $data
         * @return mixed
         * @throws \ReflectionException
         */
        public static function create(array $data)
        {
            unset($data[static::pk()]);

            static::validate($data);

            try {
                static::db()
                ->insert($data)
                ->run();
            } catch (PDOException $e) {
                $row    = $e->errorInfo[2];
                $fields = array_keys($data);

                foreach ($fields as $field) {
                    if (fnmatch("* $field*", $row)) {
                        unset($data[$field]);
                    }
                }

                return static::create($data);
            }

            return static::find(static::lastId());
        }

        public function morphMany($entityClass)
        {
            return [$entityClass, true];
        }

        public function morphOne($entityClass)
        {
            return [$entityClass, false];
        }

        public function morphTo()
        {
            return ['morphto'];
        }

        /**
         * @param $record
         * @param $entityClass
         * @return mixed
         * @throws \ReflectionException
         */
        public function pivot($record, $entityClass)
        {
            return $this->pivots($record, $entityClass, false);
        }

        /**
         * @param $record
         * @param $entityClass
         * @param bool $many
         * @return mixed
         * @throws \ReflectionException
         */
        public function pivots($record, $entityClass, $many = true)
        {
            $otherEntity = maker($entityClass);

            $tables = [$this->table(), $otherEntity->table()];

            sort($tables);

            $pivot = implode('', $tables);

            $pivotEntity = (new Entity)->setTable($pivot);

            $getter = getter($this->pk());

            $query = $pivotEntity
            ->where(
                $this->table() . '_id',
                $record->$getter()
            );

            if ($many) {
                return $query->get();
            }

            return $query->first();
        }

        /**
         * @param $m
         * @param $a
         * @return mixed|null|Record
         * @throws \ReflectionException
         */
        public static function __callStatic($m, $a)
        {
            $instance = static::db();

            if ('new' === $m) {
                return static::create(current($a));
            }

            $result = call_user_func_array([$instance, $m], $a);

            if (
                $m !== 'lastId'
                && !fnmatch('*ith*', $m)
                && (startsWith($m, 'find') || startsWith($m, 'first') || fnmatch('last*', $m))
            ) {
                return $result ? static::model($result) : null;
            }

            return $result;
        }

        /**
         * @param $m
         * @param $a
         * @return mixed|null|Record
         * @throws \ReflectionException
         */
        public function __call($m, $a)
        {
            $instance = static::db();

            if ('new' === $m) {
                return static::create(current($a));
            }

            $result = call_user_func_array([$instance, $m], $a);

            if (
                $m !== 'lastId'
                && !fnmatch('*ith*', $m)
                && (startsWith($m, 'find') || startsWith($m, 'first') || fnmatch('last*', $m))
            ) {
                return $result ? static::model($result) : null;
            }

            return $result;
        }

        /**
         * @return FastFactory
         * @throws \ReflectionException
         */
        public static function factory()
        {
            return new FastFactory(get_called_class(), self::called());
        }
    }
