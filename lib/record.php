<?php
    namespace Octo;

    class Record extends \ArrayObject implements \ArrayAccess
    {
        use Notifiable;

        protected $entity = null, $data = [], $initial = [], $callbacks = [];

        public function __construct(array $data = [], $entity)
        {
            $this->data     = $data;
            $this->initial  = $data;
            $this->entity   = $entity;

            if ($pk = isAke($data, $entity->pk(), null)) {
                actual('orm.fields.' . $entity->pk(), array_keys($data));
            }

            $methods = get_class_methods($entity);

            if (in_array('activeRecord', $methods)) {
                $entity->activeRecord($this);
            }

            $traits = class_uses($entity);

            if (!empty($traits)) {
                foreach ($traits as $trait) {
                    $tab = explode('\\', $trait);
                    $traitName = Strings::lower(end($tab));
                    $method = lcfirst(Strings::camelize('boot_' . $traitName . '_trait'));

                    if (in_array($method, $methods)) {
                        call_user_func_array([$entity, $method], [$this]);
                    }
                }
            }

            $entity->fire('make_model', $this);
        }

        public function entity()
        {
            return $this->entity;
        }

        public function db()
        {
            return foundry(Orm::class)->table($this->entity->table());
        }

        public function checkAndSave(callable $cb)
        {
            $check = $cb($this);

            if (true === $check) {
                return $this->save();
            }

            return $check;
        }

        public function fill(array $data = [])
        {
            foreach ($data as $k => $v) {
                $this->set($k, $v);
            }

            return $this;
        }

        public function merge(array $data = [])
        {
            $this->data = array_merge($this->data, $data);

            return $this;
        }

        public function contains($key)
        {
            return 'octodummy' != $this->get($key, 'octodummy');
        }

        public function collection()
        {
            return coll($this->data);
        }

        public function __call($m, $a)
        {
            if ('array' == $m) {
                return $this->data;
            }

            $c = isAke($this->callbacks, $m, null);

            if ($c) {
                if (is_callable($c)) {
                    return call_user_func_array($c, array_merge($a, [$this]));
                }
            } else {
                if ($m == 'new') {
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

                if (substr($m, 0, 3) == 'set' && strlen($m) > 3) {
                    $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($m, 3)));
                    $field              = Strings::lower($uncamelizeMethod);

                    $v = array_shift($a);

                    return $this->set($field, $v);
                }

                if (substr($m, 0, 3) == 'get' && strlen($m) > 3) {
                    $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($m, 3)));
                    $field              = Strings::lower($uncamelizeMethod);

                    $d = array_shift($a);

                    return $this->get($field, $d);
                }

                if (substr($m, 0, 3) == 'has' && strlen($m) > 3) {
                    $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($m, 3)));
                    $field              = Strings::lower($uncamelizeMethod);

                    return $this->has($field);
                }

                if (substr($m, 0, 3) == 'del' && strlen($m) > 3) {
                    $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($m, 3)));
                    $field              = Strings::lower($uncamelizeMethod);

                    return $this->del($field);
                }

                if ('octodummy' == isAke($this->data, $m, 'octodummy') && 'octodummy' == isAke($this->callbacks, $m, 'octodummy') && $this->exists()) {
                    $methods = get_class_methods($this->entity);

                    if (fnmatch('*s', $m) && in_array($m, $methods)) {
                        $class = $this->entity->$m();

                        if (is_callable($class) && !is_string($class)) {
                            return call_user_func_array($class, [$this]);
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

                        $entity = maker($class);

                        $pk = $entity->table() . '_id';

                        if ($fk = $this->get($pk, null)) {
                            $m = array_shift($a);

                            if (!$m) {
                                return $entity->find($fk);
                            } else {
                                return $this->sync($m);
                            }
                        } else {
                            if (isset($this->morph_type) && $this->morph_type == $class) {
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

                if (count($a) == 1) {
                    $concern = current($a);

                    return $this->set($m, $concern);
                }
            }
        }

        public function toArray()
        {
            return $this->data;
        }

        public function toJson()
        {
            return json_encode($this->toArray());
        }

        public function json()
        {
            echo $this->toJson();
        }

        public function exists()
        {
            return 'octodummy' != isAke($this->data, $this->entity->pk(), 'octodummy');
        }

        public function set($k, $v)
        {
            if ($k == 'password') {
                $v = lib('hasher')->make($v);
            }

            if (is_callable($v)) {
                $this->callbacks[$k] = $v;
            } else {
                Arrays::set($this->data, $k, value($v));
            }

            return $this;
        }

        public function get($k, $d = null)
        {
            if ('octodummy' == isAke($this->data, $k, 'octodummy') && 'octodummy' == isAke($this->callbacks, $k, 'octodummy') && $this->exists()) {
                $methods = get_class_methods($this->entity);

                if (fnmatch('*s', $k) && in_array($k, $methods)) {
                    $class = $this->entity->$k();

                    if (is_callable($class) && !is_string($class)) {
                        return call_user_func_array($class, [$this]);
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

                    $entity = maker($class);

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

        public function __get($k)
        {
            return $this->get($k);
        }

        public function __set($k, $v)
        {
            return $this->set($k, $v);
        }

        public function __isset($k)
        {
            return $this->has($k);
        }

        public function has($k)
        {
            return 'octodummy' != $this->get($k, 'octodummy');
        }

        public function __unset($k)
        {
            unset($this->data[$l]);
        }

        public function offsetSet($key, $value)
        {
            return $this->set($key, $value);
        }

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

        public function offsetGet($key)
        {
            return $this->get($key);
        }

        public function isDirty()
        {
            return $this->initial != $this->data;
        }

        public function dirty()
        {
            $dirty = [];

            if  ($this->initial != $this->data) {
                foreach ($this->data as $k => $v) {
                    if ($this->initial[$l] != $v) {
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

        protected function clean()
        {
            $fields = actual('orm.fields.' . $this->entity->pk());

            if ($fields) {
                $filled = array_keys($this->data);

                foreach ($filled as $field) {
                    if (!in_array($field, $fields)) {
                        unset($this->data[$field]);
                    }
                }
            }

            $this->entity()->fire('clean', $this);
        }

        protected function validate()
        {
            $this->entity()->fire('validate', $this);
        }

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
                ->run();

                $return = $this;

                $this->entity()->fire('updated', $return);
            } else {
                $this->entity()->fire('creating', $this);

                $return = $this->entity->create($this->data);

                $this->entity()->fire('created', $return);
            }

            $this->entity()->fire('saved', $return);

            return $return;
        }

        public function saveOrFail()
        {
            return $this->db()->transaction(function () {
                return $this->save();
            });
        }

        public function delete($fire = true)
        {
            if ($this->exists()) {
                if ($fire && $this->entity()->fire('deleting', $this, true) === false) return false;

                $status = $this->db()
                ->delete()
                ->where($this->entity->pk(), $this->get($this->entity->pk()))
                ->run()
                ->rowCount();

                $check = $status > 0;

                return $fire ? $this->entity()->fire('deleted', $check, true) : $check;
            }

            return false;
        }

        public function post($only = [])
        {
            foreach ($_POST as $k => $v) {
                if (!empty($only) && !in_array($k, $only)) {
                    continue;
                }

                $setter = setter($k);
                $this->$setter($v);
            }

            return $this->save();
        }

        public function copy()
        {
            if ($this->exists()) {
                $row = $this->data;

                unset($row['id']);
                unset($row['created_at']);
                unset($row['updated_at']);

                return $this->entity->create($row);
            }

            return null;
        }

        public function sync($record)
        {
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

                $getter = getter($record->entity()->pk());

                $idRecord = $record->$getter();

                $row = [
                    $this->entity->table() . '_id'      => $idValue,
                    $record->entity()->table() . '_id'  => $idRecord
                ];

                $pivotEntity->firstOrCreate($row);
            }
        }

        public function pivot($entityClass)
        {
            return $this->pivots($entityClass, false);
        }

        public function pivots($entityClass, $many = true)
        {
            $otherEntity = maker($entityClass);

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

        public function morphTo($class)
        {
            if ($this->exists()) {
                $entity = maker($class);

                return $entity->model([
                    'morph_id'      => $this->get($this->entity->pk()),
                    'morph_type'    => get_class($this->entity)
                ]);
            }
        }

        public function morphWith($model)
        {
            if ($model->exists()) {
                $entity = $model->entity();
                $getter = getter($entity->pk());

                $this->morph_id     = $model->$getter();
                $this->morph_type   = get_class($entity);
            }

            return $this;
        }
    }
