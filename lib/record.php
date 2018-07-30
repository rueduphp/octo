<?php
    namespace Octo;

    use ArrayAccess;
    use ArrayObject;

    class Record extends ArrayObject implements ArrayAccess
    {
        use Notifiable, Eventable, Hookable;

        /**
         * @var Entity
         */
        protected $entity = null;

        protected $data = [], $initial = [], $callbacks = [];

        /**
         * @param array $data
         * @param $entity
         * @param bool $isProxy
         *
         * @throws \ReflectionException
         */
        public function __construct(array $data = [], $entity, $isProxy = false)
        {
            $this->data     = $data;
            $this->initial  = $data;
            $this->entity   = $entity;

            if ($pk = isAke($data, $entity->pk(), null)) {
                actual('orm.fields.' . $entity->table(), array_keys($data));
            }

            $methods = get_class_methods($entity);

            if (in_array('activeRecord', $methods)) {
                $entity->activeRecord($this);
            }

            $traits = allClasses($entity);

            if (!empty($traits)) {
                foreach ($traits as $trait) {
                    $tab        = explode('\\', $trait);
                    $traitName  = Strings::lower(end($tab));
                    $method     = lcfirst(Strings::camelize('boot_' . $traitName . '_trait'));

                    if (in_array($method, $methods)) {
                        $params = [$entity, $method, $this];

                        return gi()->call(...$params);
                    }

                    $method = lcfirst(Strings::camelize('boot_' . $traitName));

                    if (in_array($method, $methods)) {
                        $params = [$entity, $method, $this];

                        return gi()->call(...$params);
                    }
                }
            }

            $entity->fire('model', $this);
        }

        /**
         * @return Record
         * @throws \ReflectionException
         */
        public function original()
        {
            return new self($this->initial, $this->entity);
        }

        /**
         * @param $data
         * @throws \ReflectionException
         */
        public function proxy($data)
        {
            $class = str_replace('\\', '_', get_class($this->entity)) . 'Record';

            if (!class_exists($class)) {
                $code = 'namespace Octo; class ' . $class . ' extends Record {}';

                eval($code);
            }

            actual('orm.proxy.' . $class, gi()->factory('Octo\\' . $class, $data, $this->entity, true));
        }

        /**
         * @return Entity
         */
        public function entity()
        {
            return $this->entity;
        }

        /**
         * @return Orm
         * @throws \ReflectionException
         */
        public function db(): Orm
        {
            return gi()->factory(Orm::class)->table($this->entity->table());
        }

        /**
         * @param callable $cb
         *
         * @return mixed|null|Record
         *
         * @throws \ReflectionException
         */
        public function checkAndSave(callable $cb)
        {
            $check = callCallable($cb, $this);

            if (true === $check) {
                return $this->save();
            }

            return $check;
        }

        /**
         * @param array $data
         * @return Record
         */
        public function fill(array $data = []): self
        {
            foreach ($data as $key => $value) {
                $this->set($key, $value);
            }

            return $this;
        }

        /**
         * @param array $data
         * @return Record
         */
        public function merge(array $data = []): self
        {
            $this->data = array_merge($this->data, $data);

            return $this;
        }

        /**
         * @param string $key
         * @return bool
         * @throws \ReflectionException
         */
        public function contains(string $key)
        {
            return 'octodummy' !== $this->get($key, 'octodummy');
        }

        /**
         * @return Collection
         */
        public function collection()
        {
            return coll($this->data);
        }

        /**
         * @param string $m
         * @param array $a
         *
         * @return array|mixed|null|Record
         *
         * @throws \ReflectionException
         */
        public function __call(string $m, array $a)
        {
            if ('array' === $m) {
                return $this->toArray();
            }

            $c = isAke($this->callbacks, $m, null);

            if ($c) {
                if (is_callable($c)) {
                    $params = array_merge([$c], array_merge($a, [$this]));

                    return callCallable(...$params);
                }
            } else {
                if ($m === 'new') {
                    if (empty($a)) {
                        $data = [];
                    } else {
                        $data = array_shift($a);

                        if (!is_array($data)) {
                            $data = [];
                        }
                    }

                    return new self($data);
                }

                if (substr($m, 0, 3) === 'set' && strlen($m) > 3) {
                    $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($m, 3)));
                    $field              = Strings::lower($uncamelizeMethod);

                    $v = array_shift($a);

                    return $this->set($field, $v);
                }

                if (substr($m, 0, 3) === 'get' && strlen($m) > 3) {
                    $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($m, 3)));
                    $field              = Strings::lower($uncamelizeMethod);

                    $d = array_shift($a);

                    return $this->get($field, $d);
                }

                if (substr($m, 0, 3) === 'has' && strlen($m) > 3) {
                    $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($m, 3)));
                    $field              = Strings::lower($uncamelizeMethod);

                    return $this->has($field);
                }

                if (substr($m, 0, 3) === 'del' && strlen($m) > 3) {
                    $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($m, 3)));
                    $field              = Strings::lower($uncamelizeMethod);

                    return $this->del($field);
                }

                if (
                    'octodummy' === isAke($this->data, $m, 'octodummy')
                    && 'octodummy' === isAke($this->callbacks, $m, 'octodummy')
                    && $this->exists()
                ) {
                    $methods = get_class_methods($this->entity);

                    if (fnmatch('*s', $m) && in_array($m, $methods)) {
                        $class = $this->entity->$m();

                        if (is_callable($class) && !is_string($class)) {
                            return call_user_func_array($class, [$this]);
                        }

                        if (!is_string($class) && is_array($class)) {
                            return call_user_func_array([$this, 'morphs'], $class);
                        }

                        $pk = $this->entity->table() . '_id';

                        if ($fk = $this->get($this->entity->pk(), null)) {
                            if (empty($a)) {
                                return $class::where($pk, $fk);
                            } else {
                                $rows = array_shift($a);

                                foreach ($rows as $m) {
                                    $this->sync($m);
                                }

                                return $this;
                            }
                        } else {
                            return $class::where('morph_type', get_class($this->entity))
                            ->where('morph_id', $this->get($this->entity->pk()))
                            ->cursor();
                        }
                    }

                    if (in_array($m, $methods)) {
                        $class = $this->entity->$m();

                        if (is_callable($class) && !is_string($class)) {
                            return call_user_func_array($class, [$this]);
                        }

                        if (!is_string($class) && is_array($class)) {
                            return $this->morph();
                        }

                        $entity = instanciator()->singleton($class);

                        $pk = $entity->table() . '_id';

                        if ($fk = $this->get($pk, null)) {
                            $m = array_shift($a);

                            if (!$m) {
                                return $entity->find($fk);
                            } else {
                                return $this->sync($m);
                            }
                        } else {
                            if (isset($this->morph_type) && $this->morph_type === $class) {
                                $m = array_shift($a);

                                if (!$m) {
                                    return $entity->find($this->morph_id);
                                } else {
                                    return $this->morphWith($m);
                                }
                            }
                        }
                    }
                }

                if (count($a) === 1) {
                    $concern = current($a);

                    return $this->set($m, $concern);
                }

                $method = '\\Octo\\' . $m;

                if (function_exists($method)) {
                    $params = array_merge([$method], $a);

                    return callCallable(...$params);
                }
            }
        }

        /**
         * @return array
         */
        public function toArray()
        {
            $result = [];

            foreach ($this->data as $key => $value) {
                $value = arrayable($value) ? $value->toArray() : $value;

                $result[$key] = $value;
            }

            return $result;
        }

        /**
         * @param int $option
         * @return string
         */
        public function toJson($option = JSON_PRETTY_PRINT)
        {
            return json_encode($this->toArray(), $option);
        }

        /**
         * @param int $option
         */
        public function json($option = JSON_PRETTY_PRINT)
        {
            echo $this->toJson($option);
        }

        /**
         * @return bool
         * @throws \ReflectionException
         */
        public function exists()
        {
            return 'octodummy' !== isAke($this->data, $this->entity->pk(), 'octodummy');
        }

        public function set($k, $v)
        {
            $attrMethod = lcfirst(Strings::camelize('set_attribute_' . $k));
            $methods    = get_class_methods($this->entity);

            if (in_array($attrMethod, $methods)) {
                $value = call_user_func_array([$this->entity, $attrMethod], [$v, $this]);
                Arrays::set($this->data, $k, value($value));

                return $this;
            }

            if ($k === 'password') {
                $v = lib('hasher')->make($v);
            }

            if (is_callable($v)) {
                $this->callbacks[$k] = $v;
            } else {
                Arrays::set($this->data, $k, value($v));
            }

            return $this;
        }

        /**
         * @param string $k
         * @param null $d
         * @return bool|\DateTime|mixed|null|Time
         * @throws \ReflectionException
         */
        public function get(string $k, $d = null)
        {
            $methods = get_class_methods($this->entity);

            if ('octodummy' === isAke($this->data, $k, 'octodummy')) {
                $attrMethod = lcfirst(Strings::camelize('get_attribute_' . $k));

                if (in_array($attrMethod, $methods)) {
                    return call_user_func_array([$this->entity, $attrMethod], [$this]);
                }
            }

            if (
                'octodummy' === isAke($this->data, $k, 'octodummy')
                && 'octodummy' === isAke($this->callbacks, $k, 'octodummy')
                && $this->exists()
            ) {
                if (fnmatch('*s', $k) && in_array($k, $methods)) {
                    $class = $this->entity->$k();

                    if (is_callable($class) && !is_string($class)) {
                        return call_user_func_array($class, [$this]);
                    }

                    if (!is_string($class) && is_array($class)) {
                        return call_user_func_array([$this, 'morphs'], $class);
                    }

                    $pk = $this->entity->table() . '_id';

                    if ($fk = $this->get($this->entity->pk(), null)) {
                        return $class::where($pk, $fk)->cursor();
                    } else {
                        return $class::where('morph_type', get_class($this->entity))
                        ->where('morph_id', $this->get($this->entity->pk()))
                        ->cursor();
                    }
                }

                if (in_array($k, $methods)) {
                    $class = $this->entity->$k();

                    if (is_callable($class) && !is_string($class)) {
                        return call_user_func_array($class, [$this]);
                    }

                    if (!is_string($class) && is_array($class)) {
                        return $this->morph();
                    }

                    $entity = instanciator()->factory($class);

                    $pk = $entity->table() . '_id';

                    if ($fk = $this->get($pk, null)) {
                        $model = $entity->find($fk);
                    } else {
                        if (isset($this->morph_type) && $this->morph_type == $class) {
                            return $entity->find($this->morph_id);
                        }
                    }

                    return $model;
                }
            }

            $value = value(
                Arrays::get(
                    $this->data,
                    $k,
                    value($d)
                )
            );

            if (fnmatch('*_at', $k)) {
                return Time::createFromFormat('Y-m-d H:i:s', $value);
            }

            return $value;
        }

        /**
         * @param $key
         * @return bool|\DateTime|mixed|null|Time
         * @throws \ReflectionException
         */
        public function __get($key)
        {
            return $this->get($key);
        }

        public function __set($key, $v)
        {
            return $this->set($key, $v);
        }

        /**
         * @param $key
         * @return bool
         * @throws \ReflectionException
         */
        public function __isset($key)
        {
            return $this->has($key);
        }

        /**
         * @param $key
         * @return bool
         * @throws \ReflectionException
         */
        public function has($key)
        {
            return 'octodummy' !== $this->get($key, 'octodummy');
        }

        public function __unset($key)
        {
            unset($this->data[$key]);
        }

        public function offsetSet($key, $value)
        {
            return $this->set($key, $value);
        }

        /**
         * @param mixed $key
         * @return bool
         * @throws \ReflectionException
         */
        public function offsetExists($key)
        {
            return $this->has($key);
        }

        public function offsetUnset($key)
        {
            unset($this->data[$key]);
        }

        public function del($key)
        {
            unset($this->data[$key]);
        }

        /**
         * @param mixed $key
         * @return bool|\DateTime|mixed|null|Time
         * @throws \ReflectionException
         */
        public function offsetGet($key)
        {
            return $this->get($key);
        }

        public function isDirty()
        {
            return $this->initial !== $this->data;
        }

        public function dirty()
        {
            $dirty = [];

            if ($this->initial !== $this->data) {
                foreach ($this->data as $k => $v) {
                    if ($this->initial[$k] !== $v) {
                        $dirty[$k] = $v;
                    }
                }
            }

            return $dirty;
        }

        public function now()
        {
            return date('Y-m-d H:i:s');
        }

        /**
         * @throws \ReflectionException
         */
        public function clean()
        {
            $this->entity()->fire('cleaning', $this);

            $fields = actual('orm.fields.' . $this->entity->table());

            if ($fields) {
                $filled = array_keys($this->data);

                foreach ($filled as $field) {
                    if (!in_array($field, $fields)) {
                        unset($this->data[$field]);
                    }
                }
            }

            $this->entity()->fire('cleaned', $this);
        }

        /**
         * @throws \ReflectionException
         */
        public function validate()
        {
            $this->entity()->fire('validating', $this);

            $guarded    = $this->entity()->guarded();
            $fillable   = $this->entity()->fillable();
            $data       = $this->toArray();

            if ($this->exists()) {
                unset($data[$this->entity()->pk()]);
                unset($data['created_at']);
                unset($data['updated_at']);
            }

            $keys = array_keys($data);

            if (!is_array($guarded)) {
                if ($fillable !== $keys) {
                    foreach ($keys as $key) {
                        if (!in_array($key, $fillable)) {
                            exception(
                                'orm_entity',
                                "Field $key is not fillable in model " . get_class($this->entity()) . "."
                            );
                        }
                    }
                }
            } else {
                foreach ($guarded as $key) {
                    if (in_array($key, $keys)) {
                        exception(
                            'orm_entity',
                            "Field $key is guarded in model " . get_class($this->entity()) . "."
                        );
                    }
                }
            }

            $this->entity()->fire('validated', $this);
        }

        /**
         * @return $this|mixed|Record
         *
         * @throws \ReflectionException
         */
        public function save()
        {
            $this->clean();
            $this->validate();

            if ($this->exists() && !$this->isDirty()) {
                return $this;
            }

            $this->entity()->fire('saving', $this);

            if ($this->exists()) {
                if ($this->has('updated_at')) {
                    $this->updated_at = $this->now();
                }

                $this->entity()->fire('updating', $this);

                $this->db()
                    ->update($this->data)
                    ->where($this->entity->pk(), $this->get($this->entity->pk()))
                    ->run()
                ;

                $return = $this;

                $this->entity()->fire('updated', $return);
            } else {
                $this->entity()->fire('creating', $this);

                $return = $this->entity->create($this->data);

                $this->entity()->fire('created', $return);
            }

            $this
                ->entity()
                ->fire('saved', $return)
            ;

            return $return;
        }

        /**
         * @return mixed
         *
         * @throws \Exception
         * @throws \Throwable
         */
        public function saveOrFail()
        {
            return $this->db()->transaction(function () {
                return $this->save();
            });
        }

        /**
         * @param bool $fire
         * @return bool|mixed|null
         * @throws \ReflectionException
         */
        public function delete($fire = true)
        {
            if ($this->exists()) {
                if ($fire && $this->entity()->fire('deleting', $this, true) === false) return false;

                $status = $this->db()
                    ->delete()
                    ->where($this->entity->pk(), $this->get($this->entity->pk()))
                    ->run()
                    ->rowCount()
                ;

                $check = $status > 0;

                return $fire ? $this->entity()->fire('deleted', $check, true) : $check;
            }

            return false;
        }

        /**
         * @param array $only
         * @return mixed|Record
         * @throws \ReflectionException
         */
        public function post($only = [])
        {
            foreach ($_POST as $key => $value) {
                if (!empty($only) && !in_array($key, $only)) {
                    continue;
                }

                $setter = setter($key);
                $this->$setter($value);
            }

            return $this->save();
        }

        /**
         * @return mixed|Record
         * @throws \ReflectionException
         */
        public function copy()
        {
            if ($this->exists()) {
                $row = $this->data;

                unset($row['id']);
                unset($row['created_at']);
                unset($row['updated_at']);

                return $this->entity->create($row);
            }

            return new self($this->data, $this->entity);
        }

        /**
         * @param $records
         * @throws \ReflectionException
         */
        public function sync($records)
        {
            $records = !is_array($records) ? func_get_args() : $records;

            /** @var Record $record */
            foreach ($records as $record) {
                if ($this->exists() && $record->exists()) {
                    $tables = [$this->entity->table(), $record->entity()->table()];

                    sort($tables);

                    $pivot = implode('', $tables);

                    $pivotEntity = actual("orm.entity.$pivot");

                    if (!$pivotEntity) {
                        $pivotEntity = (new Entity)->setTable($pivot);
                    }

                    $getter = getter($this->entity->pk());

                    $idValue = $this->$getter();

                    $pivotEntity
                        ->delete()
                        ->where($this->entity->table() . '_id', $idValue)
                        ->run()
                    ;

                    $getter = getter($record->entity()->pk());

                    $idRecord = $record->$getter();

                    $row = [
                        $this->entity->table() . '_id'      => $idValue,
                        $record->entity()->table() . '_id'  => $idRecord
                    ];

                    $pivotEntity->firstOrCreate($row);
                }
            }
        }

        /**
         * @param $entityClass
         * @return mixed
         * @throws \ReflectionException
         */
        public function morphOne($entityClass)
        {
            return $this->morphs($entityClass, false);
        }

        /**
         * @param $entityClass
         * @return mixed
         * @throws \ReflectionException
         */
        public function morphMany($entityClass)
        {
            return $this->morphs($entityClass);
        }

        /**
         * @param $entityClass
         * @param bool $many
         * @return mixed
         * @throws \ReflectionException
         */
        public function morphs($entityClass, $many = true)
        {
            $morphEntity = gi()->factory($entityClass);

            $getter = getter($this->entity->pk());

            $query = $morphEntity
            ->where(
                'morph_id',
                $this->$getter()
            )->where(
                'morph_type',
                get_class($this->entity)
            );

            if ($many) {
                return $query->cursor();
            }

            return $query->first();
        }

        /**
         * @return null
         * @throws \ReflectionException
         */
        public function morph()
        {
            if ($this->exists()) {
                $entity = instanciator()->factory($this->morph_type);

                return $entity->find((int) $this->morph_id);
            }

            return null;
        }

        /**
         * @param $entityClass
         * @return mixed
         * @throws \ReflectionException
         */
        public function pivot($entityClass)
        {
            return $this->pivots($entityClass, false);
        }

        /**
         * @param $entityClass
         * @param bool $many
         * @return mixed
         * @throws \ReflectionException
         */
        public function pivots($entityClass, $many = true)
        {
            $otherEntity = instanciator()->factory($entityClass);

            $tables = [$this->entity->table(), $otherEntity->table()];

            sort($tables);

            $pivot = implode('', $tables);

            $pivotEntity = actual("orm.entity.$pivot");

            if (!$pivotEntity) {
                $pivotEntity = (new Entity)->setTable($pivot);

                actual("orm.entity.$pivot", $pivotEntity);
            }

            $getter = getter($this->entity->pk());

            $query = $pivotEntity
            ->where(
                $this->entity->table() . '_id',
                $this->$getter()
            );

            if ($many) {
                return $query->cursor();
            }

            return $query->first();
        }

        /**
         * @param $class
         * @return Record
         * @throws \ReflectionException
         */
        public function morphTo($class)
        {
            if ($this->exists()) {
                /** @var Entity  $entity */
                $entity = instanciator()->singleton($class);

                return $entity->model([
                    'morph_id'      => $this->get($this->entity->pk()),
                    'morph_type'    => get_class($this->entity)
                ]);
            }
        }

        /**
         * @param Record $model
         * @return $this
         * @throws \ReflectionException
         */
        public function morphWith(Record $model)
        {
            if ($model->exists()) {
                /** @var Entity  $entity */
                $entity = $model->entity();
                $getter = getter($entity->pk());

                $this->morph_id     = $model->$getter();
                $this->morph_type   = get_class($entity);
            }

            return $this;
        }

        /**
         * @return bool|\DateTime|mixed|null|Time
         *
         * @throws \ReflectionException
         */
        public function getKey()
        {
            return $this->get($this->entity->pk());
        }
    }
