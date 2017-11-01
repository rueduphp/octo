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
            if ($this->hasModel() && $this->exists()) {
                $versions   = isAke($this->initial, 'versions', []);
                $versions[count($versions) + 1] = $this->initial;

                $this->data['versions'] = $versions;

                return $this->save();
            }

            return $this;
        }

        public function versions()
        {
            if ($this->hasModel() && $this->exists()) {
                return isAke($this->initial, 'versions', []);
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
            return engine($this->db(), $model, $this->driver())->findOrFail((int) $this->data[$model . '_id'])->now();
        }

        public function hasModel()
        {
            return 'octodummy' != isAke($this->callbacks, "table", 'octodummy');
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

        public function fill($data = [])
        {
            $data = arrayable($data) ? $data->toArray() : $data;

            foreach ($data as $k => $v) {
                $this->set($k, $v);
            }

            return $this;
        }

        public function merge($data = [])
        {
            $data = arrayable($data) ? $data->toArray() : $data;

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
            if ('actualValue' == $m) {
                $default = null;

                if (count($a) == 2) {
                    $default = $a[1];
                }

                if ($this->exists()) {
                    return isAke($this->initial, current($a), $default);
                } else {
                    return $default;
                }
            }

            if ('array' == $m) {
                return $this->data;
            }

            if ('getCacheKey' == $m) {
                if ($this->hasModel()) {
                    if ($this->exists()) {
                        return sha1(
                            $this->db() .
                            $this->table() .
                            serialize($this->data)
                        );
                    }
                }
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
                            $bank       = isAke($this->callbacks, "bank",     null);

                            if ($adapter) {
                                return lib(
                                    'eav',
                                    [$this->db(), $fktable, $this->adapter()]
                                )->find((int) $this->get($fktable . '_id'), $o);
                            }

                            if ($bank) {
                                if (fnmatch('*s', $fktable)) {
                                    $fk = $this->table() . '_id';
                                    $fkParent = substr($fktable, 0, -1);

                                    return (new Bank($this->db(), $fkParent, $this->engine()))
                                    ->where($fk, (int) $this->get('id'));
                                } else {
                                    $id = isAke($this->data, $fktable . '_id', 'octodummy');

                                    if (is_numeric($id)) {
                                        return (
                                            new Bank($this->db(), $fktable, $this->engine())
                                        )->findHydrate((int) $id, $o);
                                    } else {
                                        $fk = $this->table() . '_id';

                                        return (new Bank($this->db(), $fktable, $this->engine()))
                                        ->where($fk, (int) $this->get('id'))
                                        ->firstHydrate();
                                    }
                                }
                            }

                            if ($driver) {
                                $engine = getEngine();

                                if (fnmatch('*s', $fktable)) {
                                    $fk = $this->table() . '_id';
                                    $fkParent = substr($fktable, 0, -1);

                                    if (is_null($model)) {
                                        $model = true;
                                    } else {
                                        if (true !== $model) {
                                            $model = false;
                                        }
                                    }

                                    return $engine($this->db(), $fkParent)
                                    ->where($fk, (int) $this->get('id'));
                                } else {
                                    $id = isAke($this->data, $fktable . '_id', 'octodummy');

                                    if (is_numeric($id)) {
                                        return $engine($this->db(), $fktable)->find((int) $id, $o);
                                    } else {
                                        $fk = $this->table() . '_id';

                                        if (is_null($model)) {
                                            $model = true;
                                        } else {
                                            if (true !== $model) {
                                                $model = false;
                                            }
                                        }

                                        return $engine($this->db(), $fktable)
                                        ->where($fk, (int) $this->get('id'))
                                        ->first($model);
                                    }
                                }
                            }
                        }
                    }
                } else {
                    if (count($a) == 1) {
                        $arg = current($a);

                        if ($arg instanceof \Closure) {
                            return $this->fn($m, $arg);
                        } else {
                            return $this->set($m, $arg);
                        }
                    }
                }
            }

            $method = '\\Octo\\' . $m;

            if (function_exists($method)) {
                return call_user_func_array($method, $a);
            }
        }

        /**
         * Return fresh object from database
         * @return Object
         */
        public function fresh()
        {
            if ($this->hasModel() && $this->exists()) {
                return engine($this->db(), $this->table(), $this->driver())
                ->find((int) $this->data['id']);
            }

            return new self;
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
            return is_numeric(isAke($this->data, 'id', 'octodummy'));
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

        public function set($k, $v)
        {
            $driver = isAke($this->callbacks, "driver", null);

            if ($driver) {
                $entity = $this->instance()->entity();

                if ($entity) {
                    $attrMethod = lcfirst(Strings::camelize('set_attribute_' . $k));
                    $methods    = get_class_methods($entity);

                    if (in_array($attrMethod, $methods)) {
                        $value = call_user_func_array([$entity, $attrMethod], [$v, $this]);
                        Arrays::set($this->data, $k, value($value));

                        return $this;
                    }
                }
            }

            if (fnmatch('*s', $k) && $driver) {
                if (is_array($v) && !empty($v)) {
                    $first = current($v);

                    if ($first instanceof self) {
                        foreach ($v as $model) {
                            $setter = setter($this->table() . '_id');
                            $model->$setter($this->get('id'))->save();
                        }

                        return $this;
                    }
                }
            }

            if ($driver && $v instanceof self) {
                return $this->set($k . '_id', $v->id);
            }

            if ($k == 'password') {
                $v = lib('hasher')->make($v);
            }

            Arrays::set($this->data, $k, value($v));

            return $this;
        }

        public function get($k, $d = null)
        {
            $bank = isAke($this->callbacks, "bank", null);
            $driver = isAke($this->callbacks, "driver", null);

            if ($bank && !isset($this->data[$k])) {
                if (fnmatch('*s', $k)) {
                    $fk = $this->table() . '_id';
                    $fkParent = substr($k, 0, -1);

                    $query = (new Bank($this->db(), $fkParent, $this->engine()))
                        ->where($fk, (int) $this->get('id'));

                    if ($query->count() > 0) {
                        return $query;
                    }
                } else {
                    $id = isAke($this->data, $k . '_id', 'octodummy');

                    if (is_numeric($id)) {
                        return (new Bank($this->db(), $k, $this->engine()))
                            ->find((int) $this->get($k . '_id'));
                    } else {
                        $fk = $this->table() . '_id';

                        $query = (new Bank($this->db(), $k, $this->engine()))
                            ->where($fk, (int) $this->get('id'));

                        if ($query->count() > 0) {
                            return $query->first();
                        }
                    }
                }
            }

            if ($driver && !isset($this->data[$k])) {
                $entity = $this->instance()->entity();

                if ($entity) {
                    $attrMethod = lcfirst(Strings::camelize('get_attribute_' . $k));
                    $methods    = get_class_methods($entity);

                    if (in_array($attrMethod, $methods)) {
                        return call_user_func_array([$entity, $attrMethod], [$this]);
                    }
                }

                $engine = getEngine();

                if (fnmatch('*s', $k)) {
                    $fk = $this->table() . '_id';
                    $fkParent = substr($k, 0, -1);

                    $query = $engine($this->db(), $fkParent)
                    ->where($fk, (int) $this->get('id'));

                    if ($query->count() > 0) {
                        return $query;
                    }
                } else {
                    $id = isAke($this->data, $k . '_id', 'octodummy');

                    if (is_numeric($id)) {
                        return $engine($this->db(), $k)
                        ->find((int) $this->get($k . '_id'));
                    } else {
                        $fk = $this->table() . '_id';

                        $query = $engine($this->db(), $k)
                        ->where($fk, (int) $this->get('id'));

                        if ($query->count() > 0) {
                            return $query->first();
                        }
                    }
                }
            }

            if (in_array($k, ['created_at', 'updated_at'])) {
                return Time::createFromTimestamp(isAke($this->data, $k, time()));
            }

            return value(
                Arrays::get(
                    $this->data,
                    $k,
                    value($d)
                )
            );
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
            if ($this->hasModel() && $this->exists() && !isset($this->data[$k])) {
                if (fnmatch('*s', $k)) {
                    $fk = $this->table() . '_id';
                    $fkParent = substr($k, 0, -1);

                    $query = engine($this->db(), $fkParent, $this->driver())->where([$fk, '=', (int) $this->get('id')]);

                    return $query->count() > 0;
                } else {
                    $id = $this->get($k . '_id', 'octodummy');

                    if (is_numeric($id)) {
                        return !empty(engine($this->db(), $k, $this->driver())->row((int) $this->get($k . '_id')));
                    }
                }
            }

            return 'octodummy' != $this->get($k, 'octodummy');
        }

        public function incr($k, $by = 1)
        {
            $old = $this->get($k, 0);
            $new = $old + $by;

            return $this->set($k, $new);
        }

        public function decr($k, $by = 1)
        {
            $old = $this->get($k, 0);
            $new = $old - $by;

            return $this->set($k, $new);
        }

        public function __unset($k)
        {
            if ($this->hasModel() && $this->exists() && !$this->has($k)) {
                if (fnmatch('*s', $k)) {
                    $fk = $this->table() . '_id';
                    $fkParent = substr($k, 0, -1);

                    $rows = engine($this->db(), $fkParent, $this->driver())->where([$fk, '=', (int) $this->get('id')]);

                    if ($rows->count() > 0) {
                        $rows->delete();
                    }
                } else {
                    $id = $this->get($k . '_id', 'octodummy');

                    if (is_numeric($id)) {
                        $row = engine($this->db(), $k, $this->driver())->find((int) $this->get($k . '_id'));

                        if ($row) {
                            $row->delete();
                        }
                    }
                }
            } else {
                unset($this->data[$k]);
            }
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

            $db = fnmatch('*s', $fk)
            ? engine($this->db(), substr($fk, 0, -1), $this->driver())
            : engine($this->db(), $fk, $this->driver());

            return $db->where([$this->table() . '_id', '=', (int) $this->data['id']]);
        }

        public function through(Octalia $db1, Octalia $db2)
        {
            if (!$this->exists()) {
                exception('model', 'id must be defined to use through.');
            }

            $fk = $this->table() . '_id';

            $sub = $db1->where([$fk, '=', (int) $this->data['id']])->get();

            $ids = [];

            foreach ($sub as $row) {
                $ids[] = $row['id'];
            }

            $fk2 = $db1->table . '_id';

            return $db2->where([$fk2, 'IN', implode(',', $ids)])->get();
        }

        public function hasThrough(Octalia $db1, Octalia $db2)
        {
            if (!$this->exists()) {
                exception('model', 'id must be defined to use hasThrough.');
            }

            return $this->countThrough($db1, $db2) > 0;
        }

        public function countThrough(Octalia $db1, Octalia $db2)
        {
            if (!$this->exists()) {
                exception('model', 'id must be defined to use countThrough.');
            }

            $database = $this->db();

            $fk = $this->table() . '_id';

            $sub = $db1->where([$fk, '=', (int) $this->data['id']])->get();

            $ids = [];

            foreach ($sub as $row) {
                $ids[] = $row['id'];
            }

            $fk2 = $db1->table . '_id';

            return $db2->where([$fk2, 'IN', implode(',', $ids)])->count();
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
            return Time::createFromTimestamp(isAke($this->data, 'created_at', time()));
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
            }

            return $this;
        }

        public function dissociate(Object $model)
        {
            if ($this->hasModel() && $this->exists()) {
                $field  = $model->table() . '_id';

                unset($this->data[$field]);
            }

            return $this;
        }

        public function adjust($field, callable $cb = null)
        {
            $key = $field ?: $cb();

            $this->data[$field] = $key;

            return $this;
        }

        public function validate()
        {
            if ($this->hasModel() && $this->exists()) {
                $check = engine($this->db(), $this->table(), $this->driver())
                ->validator()
                ->check($this->toArray());

                if ($check) {
                    $this->save();

                    return true;
                }

                return $check;
            }

            return false;
        }

        public function polymorph(Object $polymorph = null)
        {
            if ($this->hasModel() && $this->exists()) {
                if (is_null($polymorph)) {
                    return engine(
                        $this->db(),
                        $this->polymorph_type,
                        $this->driver()
                    )->find((int) $this->polymorph_id);
                }

                $this->data['polymorph_type'] = $polymorph->table();
                $this->data['polymorph_id'] = (int) $polymorph->id;
            }

            return $this;
        }

        public function polymorphs($parent)
        {
            if ($this->hasModel() && $this->exists()) {
                return engine($this->db(), $parent, $this->driver())
                ->where('polymorph_type', $this->table())
                ->where('polymorph_id', (int) $this->id);
            }

            return $this;
        }

        public function relationship(Octalia $model, $many = true)
        {
            if ($this->hasModel() && $this->exists()) {
                $fk = $model->table;
                $idFk = $fk . '_id';

                if (isset($this->data[$idFk]) && is_numeric($this->data[$idFk])) {
                    return engine(
                        $this->db(),
                        $fk,
                        $this->driver()
                    )->find((int) $this->data[$idFk]);
                } else {
                    $query = engine(
                        $this->db(),
                        $fk,
                        $this->driver()
                    )->where($this->table() . '_id', (int) $this->get('id'));

                    return $many ? $query : $query->first(true);
                }
            }

            return $this;
        }

        public function hasOne(Octalia $model)
        {
            return $this->relationship($model, false);
        }

        public function belongsTo(Octalia $model)
        {
            return $this->relationship($model, false);
        }

        public function hasMany(Octalia $model)
        {
            return $this->relationship($model);
        }

        public function belongsToMany(Octalia $model)
        {
            return $this->relationship($model);
        }

        public function manyToMany(Octalia $model)
        {
            if ($this->hasModel() && $this->exists()) {
                $tables = [$this->table(), $model->table];
                sort($tables);
                $pivot = implode('', $tables);

                return engine($this->db(), $pivot, $this->driver())
                ->where($this->table() . '_id', (int) $this->get('id'));
            }

            return $this;
        }

        public function pivoted(Octalia $model)
        {
            if ($this->hasModel() && $this->exists()) {
                $relations = $this->manyToMany($model);
                $ids = [];

                if ($relations->count()) {
                    foreach ($relations->get() as $relation) {
                        $ids[] = $relation[$model->table . '_id'];
                    }
                }

                if (empty($ids)) {
                    return engine(
                        $this->db(),
                        $model->table,
                        $this->driver()
                    )->where(['id', '<', 0]);
                } else {
                    return engine(
                        $this->db(),
                        $model->table,
                        $this->driver()
                    )->where(['id', 'IN', $ids]);
                }
            }

            return $this;
        }

        public function attach(Object $model, array $data = [], $sync = false)
        {
            if ($this->hasModel() && $this->exists() && $model->hasModel() && $model->exists()) {
                $tables = [$this->table(), $model->table()];
                sort($tables);
                $pivot = implode('', $tables);

                if ($sync) {
                    $relation = engine(
                        $this->db(),
                        $pivot,
                        $this->driver()
                    )->firstOrCreate([
                        $this->table() . '_id' => (int) $this->get('id'),
                        $model->table() . '_id' => (int) $model->id
                    ]);
                } else {
                    $relation = engine(
                        $this->db(),
                        $pivot,
                        $this->driver()
                    )->store([
                        $this->table() . '_id' => (int) $this->get('id'),
                        $model->table() . '_id' => (int) $model->id
                    ]);
                }

                if (!empty($data)) {
                    $relation->fill($data)->save();
                }
            }

            return $this;
        }

        public function sync(Object $model, array $data = [])
        {
            return $this->attach($model, $data, true);
        }

        public function detach(Object $model)
        {
            if ($this->hasModel() && $this->exists() && $model->hasModel() && $model->exists()) {
                $tables = [$this->table(), $model->table()];
                sort($tables);
                $pivot = implode('', $tables);

                return engine(
                    $this->db(),
                    $pivot,
                    $this->driver()
                )
                ->where($this->table() . '_id', (int) $this->get('id'))
                ->where($model->table() . '_id', (int) $model->id)
                ->delete();
            }

            return $this;
        }

        public function synchronize(Object $model)
        {
            if ($this->hasModel() && $this->exists() && $model->hasModel() && $model->exists()) {
                return Pivot::sync($this, $model);
            }
        }

        public function unsynchronize(Object $model)
        {
            if ($this->hasModel() && $this->exists() && $model->hasModel() && $model->exists()) {
                return Pivot::remove($this, $model);
            }
        }

        public function synchronized(Object $model)
        {
            if ($this->hasModel() && $this->exists() && $model->hasModel() && $model->exists()) {
                return Pivot::get($this, $model);
            }
        }

        public function pivots($em)
        {
            if ($this->hasModel() && $this->exists() && $em->hasModel() && $em->exists()) {
                return Pivot::pivoted($this, $em);
            }
        }

        public function bound(Object $model)
        {
            if ($this->hasModel() && $this->exists() && $model->hasModel() && $model->exists()) {
                return Pivot::bound($this, $model);
            }
        }

        public function add(Object $model)
        {
            if ($this->hasModel() && $this->exists() && $model->hasModel() && $model->exists()) {
                if ($this->db() != $model->db()) {
                    exception('db', "add method requires the 2 models have the same database.");
                }

                $table  = $model->table();
                $setter = setter($table . '_id');
                $this->$setter($model->id);
            }
        }

        public function isDirty()
        {
            return $this->initial != $this->data;
        }

        public function dirty()
        {
            $dirty = [];

            if ($this->initial != $this->data) {
                foreach ($this->data as $k => $v) {
                    if ($this->initial[$k] != $v) {
                        $dirty[$k] = $v;
                    }
                }
            }

            return $dirty;
        }

        public function cb($m, callable $cb)
        {
            $this->callbacks[$m] = $cb;

            return $this;
        }
    }
