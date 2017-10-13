<?php
    namespace Octo;

    use PDOException;

    class Entity
    {
        protected $table;
        protected $guarded      = null;
        protected $fillable     = [];
        protected $hidden       = [];
        protected $timestamps   = true;
        protected $softDelete   = false;
        protected $primaryKey   = 'id';

        protected static $booted = [];

        public function __construct()
        {
            $class  = get_called_class();

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

                $this->fire('booted');
            }
        }

        public function fire($event, $concern = null, $return = false)
        {
            $methods = get_class_methods($this);
            $method  = 'on' . Strings::camelize($event);

            if (in_array($method, $methods)) {
                $result = $this->$method($concern);

                if ($return) {
                    return $result;
                }
            }

            return $concern;
        }

        public function setTable($table)
        {
            $this->table = $table;

            return $this;
        }

        public static function table()
        {
            return static::called()->table;
        }

        public static function pk()
        {
            return static::called()->primaryKey;
        }

        public static function guarded()
        {
            return static::called()->guarded;
        }

        public static function fillable()
        {
            return static::called()->fillable;
        }

        public static function called()
        {
            $class  = get_called_class();
            $i      = maker($class);

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

        public static function model(array $data = [])
        {
            return new Record($data, static::called());
        }

        protected static function db()
        {
            return foundry(Orm::class)->table(static::table());
        }

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
                if ($concern == []) {
                    return $this;
                }
            }

            return get_called_class();
        }

        public static function new(array $data)
        {
            return static::create($data);
        }

        public static function validate(array $data)
        {
            $model = static::model($data);

            $model->clean();
            $model->validate();
        }

        public static function create(array $data)
        {
            unset($data[static::pk()]);

            static::validate($data);

            try {
                $new = static::db()
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

        public function pivot($record, $entityClass)
        {
            return $this->pivots($record, $entityClass, false);
        }

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

        public static function __callStatic($m, $a)
        {
            $instance = static::db();

            if ('new' == $m) {
                return static::create(current($a));
            }

            $result = call_user_func_array([$instance, $m], $a);

            if ($m != 'lastId' && !fnmatch('*ith*', $m) && (startsWith($m, 'find') || startsWith($m, 'first') || fnmatch('last*', $m))) {
                return $result ? static::model($result) : null;
            }

            return $result;
        }

        public function __call($m, $a)
        {
            $instance = static::db();

            if ('new' == $m) {
                return static::create(current($a));
            }

            $result = call_user_func_array([$instance, $m], $a);

            if ($m != 'lastId' && !fnmatch('*ith*', $m) && (startsWith($m, 'find') || startsWith($m, 'first') || fnmatch('last*', $m))) {
                return $result ? static::model($result) : null;
            }

            return $result;
        }
    }
