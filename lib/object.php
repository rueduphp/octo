<?php
    namespace Octo;

    class Object extends \ArrayObject implements \ArrayAccess
    {
        use Notifiable;

        protected $model = null, $data = [], $initial = [], $callbacks = [];
        public $hooks = ['before' => ['create' => null, 'read' => null, 'update' => null, 'delete' => null], 'after' => ['create' => null, 'read' => null, 'update' => null, 'delete' => null]];

        public function __construct(array $data = [])
        {
            $this->data     = $data;
            $this->initial  = $data;
        }

        public function getDirty()
        {
            return $this->initial;
        }

        public function repo()
        {
            if ($this->hasModel() && $this->exists()) {
                $class = '\\Octo\\' . Strings::camelize($this->db() . '_' . $this->table() . '_model');

                return new $class($this);
            }
        }

        public function versioning()
        {
            if ($this->hasModel()) {
                if ($this->exists()) {
                    $versions   = isAke($this->initial, 'versions', []);
                    $versions[count($versions) + 1] = $this->initial;

                    $this->data['versions'] = $versions;
                }

                return $this->save();
            }

            return $this;
        }

        public function versions()
        {
            if ($this->hasModel()) {
                if ($this->exists()) {
                    return isAke($this->initial, 'versions', []);
                }
            }

            return [];
        }

        public function version($index = null)
        {
            $versions = $this->versions();

            return empty($index) ? end($versions) : aget($versions, $index, []);
        }

        public function versionedAt($timestamp)
        {
            $versions = $this->versions();

            return current(
                array_values(
                    coll($versions)
                    ->where(['updated_at', '=', (int) $timestamp])
                    ->toArray()
                )
            );
        }

        function checkAndSave(callable $cb)
        {
            if ($this->hasModel()) {
                $check = $cb($this->data);

                if (true === $check) {
                    return $this->save();
                }

                return $check;
            }
        }

        public function touch($model)
        {
            return odb($this->db(), $model)->findOrFail((int) $this->data[$model . '_id'])->now();
        }

        public function hasModel()
        {
            return !empty($this->model);
        }

        public function reset()
        {
            return new self;
        }

        public function flush()
        {
            return new self;
        }

        public function populate(array $data = [])
        {
            return new self($data);
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

        public function set($k, $v)
        {
            if ($k == 'password') {
                $v = lib('hasher')->make($v);
            }

            Arrays::set($this->data, $k, value($v));

            return $this;
        }

        public function get($k, $d = null)
        {
            return value(
                Arrays::get(
                    $this->data,
                    $k,
                    value($d)
                )
            );
        }

        public function contains($k)
        {
            return 'octodummy' != $this->get($key, 'octodummy');
        }

        public function del($k)
        {
            Arrays::forget($this->data, $k);

            return $this;
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

            if ('getCacheKey' == $m) {
                if ($this->hasModel()) {
                    if ($this->exits()) {
                        return sha1(
                            $this->db() .
                            $this->table() .
                            serialize($this->data)
                        );
                    }
                }
            }

            $c = isAke($this->callbacks, $m, null);

            if ('save' == $m) {
                if ($this->initial == $this->data && $this->exists()) {
                    return $this;
                } else {
                    $db = odb($this->db(), $this->table());

                    return $db->save($this->array());
                }
            }

            if ($c) {
                if (is_callable($c)) {
                    $driver = isAke($this->callbacks, "driver", null);

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

                if (substr($m, 0, 3) == 'set') {
                    $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($m, 3)));
                    $field              = Strings::lower($uncamelizeMethod);

                    $v = array_shift($a);

                    return $this->set($field, $v);
                }

                if (substr($m, 0, 3) == 'get') {
                    $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($m, 3)));
                    $field              = Strings::lower($uncamelizeMethod);

                    $d = array_shift($a);

                    return $this->get($field, $d);
                }

                if (substr($m, 0, 3) == 'has') {
                    $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($m, 3)));
                    $field              = Strings::lower($uncamelizeMethod);

                    return $this->has($field);
                }

                if (substr($m, 0, 3) == 'del') {
                    $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($m, 3)));
                    $field              = Strings::lower($uncamelizeMethod);

                    return $this->del($field);
                }

                $table = isAke($this->callbacks, "table", null);

                if ($table) {
                    if (is_callable($table)) {
                        $table = $table();

                        $model = $o = array_shift($a);

                        if ($o instanceof self) {
                            try {
                                $fkTable = $o->table();

                                if ($fkTable) {
                                    $this->set($fkTable . '_id', $o->getId());

                                    return $this;
                                }
                            } catch (\Exception $e) {
                                return $this;
                            }
                        } else {
                            if (true !== $o && false !== $o) {
                                $o = true;
                            }

                            $fktable = Strings::lower($m);

                            $adapter    = isAke($this->callbacks, "adapter",    null);
                            $driver     = isAke($this->callbacks, "driver",     null);

                            if ($adapter) {
                                return lib('eav', [$this->db(), $fktable, $this->adapter()])->find((int) $this->get($fktable . '_id'), $o);
                            }

                            if ($driver) {
                                if (fnmatch('*s', $fktable)) {
                                    $fk = $this->table() . '_id';
                                    $fkParent = substr($fktable, 0, -1);

                                    if (!$model) {
                                        $model = false;
                                    } else {
                                        if (true !== $model) {
                                            $model = false;
                                        }
                                    }

                                    $tables = [$this->table(), $fkParent];
                                    sort($tables);

                                    Registry::set('octalia.sync', $this);

                                    return odb($this->db(), $fkParent)->where([$fk, '=', (int) $this->get('id')]);
                                } else {
                                    return odb($this->db(), $fktable)->find((int) $this->get($fktable . '_id'), $o);
                                }
                            }
                        }
                    }
                } else {
                    if (count($a) == 1) {
                        return $this->set($m, current($a));
                    }
                }
            }
        }

        /**
         * Return fresh object from database
         * @return Octo\Object
         */
        public function fresh()
        {
            if ($this->hasModel() && $this->exists()) {
                return odb($this->db(), $this->table())->find((int) $this->data['id']);
            }

            return $this;
        }

        public function attach($data)
        {
            if ($this->hasModel() && $this->exists()) {
                if ($data instanceof self) {
                    $data = [$data->getId()];
                } elseif (is_numeric($data)) {
                    $data = [(int) $data];
                }

                if (is_array($data)) {
                    $sync = Registry::get('octalia.sync', []);

                    if (!empty($sync)) {
                        $syncKey    = key($sync);
                        $syncTable  = $sync[$syncKey];

                        foreach ($data as $id) {
                            odb($this->db, $syncTable)->firstOrCreate([
                                $syncKey => $id,
                                $this->table() . '_id' => $this->get('id')
                            ]);
                        }
                    }
                }
            }

            return $this;
        }

        public function detach($data)
        {
            if ($this->hasModel() && $this->exists()) {
                if ($data instanceof self) {
                    $data = [$data->getId()];
                } elseif (is_numeric($data)) {
                    $data = [(int) $data];
                }

                if (is_array($data)) {
                    $sync = Registry::get('octalia.sync', []);

                    if (!empty($sync)) {
                        $syncKey    = key($sync);
                        $syncTable  = $sync[$syncKey];

                        foreach ($data as $id) {
                            odb($this->db, $syncTable)
                            ->where([$syncKey, '=', (int) $id])
                            ->where([$this->table() . '_id', '=', (int) $this->get('id')])
                            ->delete();
                        }
                    }
                }
            }

            return $this;
        }

        public function fn($m, callable $c)
        {
            $this->callbacks[$m] = $c;

            return $this;
        }

        public function extend($m, callable $c)
        {
            return $this->fn($m, $c);
        }

        public function macro($m, callable $c)
        {
            return $this->fn($m, $c);
        }

        public function scope($m, callable $c)
        {
            return $this->fn($m, $c);
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
            return is_numeric(isAke($this->data, 'id', 'dummy'));
        }

        public function __toString()
        {
            return $this->toJson();
        }

        public function model($name = null)
        {
            if (empty($name)) {
                return $this->model;
            }

            $this->model = $name;

            return $this;
        }

        public function hook($when, callable $cb)
        {
            Arrays::set($this->hooks, $when, $cb);

            return $this;
        }

        public function __get($k)
        {
            $driver = isAke($this->callbacks, "driver", null);

            if ($driver && !$this->has($k)) {
                if (fnmatch('*s', $k)) {
                    $fk = $this->table() . '_id';
                    $fkParent = substr($k, 0, -1);

                    $query = odb($this->db(), $fkParent)->where([$fk, '=', (int) $this->get('id')]);

                    if ($query->count() > 0) {
                        return $query;
                    }
                } else {
                    $id = $this->get($k . '_id', 'octodummy');

                    if (is_numeric($id)) {
                        return odb($this->db(), $k)->row((int) $this->get($k . '_id'));
                    }
                }
            }

            return $this->get($k);
        }

        public function __set($k, $v)
        {
            $driver = isAke($this->callbacks, "driver", null);

            if (fnmatch('*s', $k)) {
                if (is_array($v) && !empty($v)) {
                    $first = current($v);

                    if ($first instanceof self) {
                        foreach ($v as $model) {
                            $setter =setter($this->table() . '_id');
                            $model->$setter($this->get('id'))->save();
                        }

                        return $this;
                    }
                }
            }

            if ($driver && $v instanceof self) {
                return $this->set($k . '_id', $v->id);
            }

            return $this->set($k, $v);
        }

        public function __isset($k)
        {
            $driver = isAke($this->callbacks, "driver", null);

            if ($driver && !$this->has($k)) {
                if (fnmatch('*s', $k)) {
                    $fk = $this->table() . '_id';
                    $fkParent = substr($k, 0, -1);

                    if (!$model) {
                        $model = false;
                    } else {
                        if (true !== $model) {
                            $model = false;
                        }
                    }

                    $query = odb($this->db(), $fkParent)->where([$fk, '=', (int) $this->get('id')]);

                    return $query->count() > 0;
                } else {
                    $id = $this->get($k . '_id', 'octodummy');

                    if (is_numeric($id)) {
                        return !empty(odb($this->db(), $k)->row((int) $this->get($k . '_id')));
                    }
                }
            }

            return 'octodummy' != $this->get($k, 'octodummy');
        }

        public function has($k)
        {
            return 'octodummy' != $this->get($k, 'octodummy');
        }

        public function __unset($k)
        {
            $driver = isAke($this->callbacks, "driver", null);

            if ($driver && !$this->has($k)) {
                if (fnmatch('*s', $k)) {
                    $fk = $this->table() . '_id';
                    $fkParent = substr($k, 0, -1);

                    if (!$model) {
                        $model = false;
                    } else {
                        if (true !== $model) {
                            $model = false;
                        }
                    }

                    $rows = odb($this->db(), $fkParent)->where([$fk, '=', (int) $this->get('id')]);

                    if ($rows->count() > 0) {
                        $rows->delete();
                    }
                } else {
                    $id = $this->get($k . '_id', 'octodummy');

                    if (is_numeric($id)) {
                        $row = odb($this->db(), $k)->find((int) $this->get($k . '_id'));

                        if ($row) {
                            $row->delete();
                        }
                    }
                }
            } else {
                unset($this->data[$l]);
            }
        }

        public function offsetSet($key, $value)
        {
            return $this->set($key, $value);
        }

        public function offsetExists($key)
        {
            return 'octodummy' != $this->get($key, 'octodummy');
        }

        public function offsetUnset($key)
        {
            unset($this->data[$key]);
        }

        public function offsetGet($key)
        {
            return $this->get($key);
        }

        public function deleteSoft()
        {
            $this->data['deleted_at'] = time();

            $save = isAke($this->callbacks, "save", null);

            return $save ? $this->save() : $this;
        }

        public function take($fk)
        {
            if (!$this->exists()) {
                exception('model', 'id must be defined to use take.');
            }

            $db = fnmatch('*s', $fk) ? odb($this->db(), substr($fk, 0, -1)) : odb($this->db(), $fk);

            return $db->where([$this->table() . '_id', '=', (int) $this->data['id']]);
        }

        public function through($t1, $t2)
        {
            if (!$this->exists()) {
                exception('model', 'id must be defined to use through.');
            }

            $db1 = odb($this->db(), $t1);

            $fk = $this->table() . '_id';

            $sub = $db1->where([$fk, '=', (int) $this->data['id']])->get();

            $ids = [];

            foreach ($sub as $row) {
                $ids[] = $row['id'];
            }

            $fk2 = $t1 . '_id';

            return odb($this->db(), $t2)->where([$fk2, 'IN', implode(',', $ids)])->get();
        }

        public function hasThrough($t1, $t2)
        {
            if (!$this->exists()) {
                exception('model', 'id must be defined to use hasThrough.');
            }

            return $this->countThrough($t1, $t2) > 0;
        }

        public function countThrough($t1, $t2)
        {
            if (!$this->exists()) {
                exception('model', 'id must be defined to use countThrough.');
            }

            $database = $this->db();

            $db1 = odb($database, $t1);

            $fk = $this->table() . '_id';

            $sub = $db1->where([$fk, '=', (int) $this->data['id']])->get();

            $ids = [];

            foreach ($sub as $row) {
                $ids[] = $row['id'];
            }

            $fk2 = $t1 . '_id';

            return odb($database, $t2)->where([$fk2, 'IN', implode(',', $ids)])->count();
        }

        public function timestamps()
        {
            return [
                'created_at' => Time::createFromTimestamp(isAke($this->data, 'created_at', time())),
                'updated_at' => Time::createFromTimestamp(isAke($this->data, 'updated_at', time()))
            ];
        }

        public function updated()
        {
            return Time::createFromTimestamp(isAke($this->data, 'updated_at', time()));
        }

        public function created()
        {
            return Time::createFromTimestamp(isAke($this->data, 'updated_at', time()));
        }

        public function now()
        {
            if ($this->hasModel()) {
                $this->data['updated_at'] = time();

                $save = isAke($this->callbacks, "save", null);

                return $save ? $this->save() : $this;
            }
        }

        public function associate(Object $model)
        {
            if ($this->hasModel() && $this->exists()) {
                $field = $model->table() . '_id';

                $this->data[$field] = $model->id;
                $this->data[$model->table()] = $model->toArray();
            }

            return $this;
        }

        public function dissociate(Object $model)
        {
            if ($this->hasModel() && $this->exists()) {
                $field  = $model->table() . '_id';

                unset($this->data[$field]);
                unset($this->data[$model->table()]);
            }

            return $this;
        }

        public function adjust($field, $cb = null)
        {
            $key = $field ?: $cb();

            $this->data[$field] = $key;

            return $this;
        }

        public function validate()
        {
            if ($this->hasModel() && $this->exists()) {
                $check = odb($this->db(), $this->table())->validator()->check($this->toArray());

                if ($check) {
                    $this->save();

                    return true;
                }

                return $check;
            }

            return false;
        }
    }
