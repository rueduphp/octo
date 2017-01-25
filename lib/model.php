<?php
    namespace Octo;

    use ArrayAccess;

    class Model implements ArrayAccess
    {
        private $storage = [], $initial = [], $_hooks = [], $_events = [], $db = null;

        public function __construct($db = null, $data = [])
        {
            $this->storage  = $this->initial  = $data;

            if ($db) {
                $this->db = sha1(get_class($db) . $db->db() . $db->table());
                (new Now)->set($this->db, $db);
            }
        }

        public function db()
        {
            if ($this->db) {
                return (new Now)->get($this->db);
            }

            return null;
        }

        public function delete(callable $before = null, callable $after = null)
        {
            if ($db = $this->db()) {
                $id = isAke($this->storage, 'id', false);

                if (false !== $id) {
                    if (!$before) {
                        $before = isAke($this->_hooks, 'beforeDelete', null);
                    }

                    if ($before) {
                        call_user_func_array($before, [$this]);
                    }

                    $res = $this->db()->delete((int) $id);

                    if (!$after) {
                        $after = isAke($this->_hooks, 'afterDelete', null);
                    }

                    if ($after) {
                        call_user_func_array($after, [$this]);
                    }

                    return $res;
                }
            }

            return false;
        }

        public function save(callable $validate = null, callable $before = null, callable $after = null)
        {
            if ($db = $this->db()) {
                $check  = true;
                $create = false;
                $id     = isAke($this->storage, 'id', false);

                if (false !== $id) {
                    $continue = sha1(serialize($this->storage)) != sha1(serialize($this->initial));

                    if (false === $continue) {
                        return $this;
                    }
                }

                if (!$validate) {
                    $validate = isAke($this->_hooks, 'validate', null);
                }

                if ($validate) {
                    $check = call_user_func_array($validate, [$this->storage]);
                }

                if (true !== $check) {
                    exception('Model', "This model must be valid to be saved.");
                }

                if ($id) {
                    if (!$before) {
                        $before = isAke($this->_hooks, 'beforeUpdate', null);
                    }
                } else {
                    $create = true;

                    if (!$before) {
                        $before = isAke($this->_hooks, 'beforeCreate', null);
                    }
                }

                if ($before) {
                    call_user_func_array($hook, [$this]);
                }

                $row = $this->db()->save($this->storage);

                if ($create) {
                    if (!$after) {
                        $after = isAke($this->_hooks, 'afterCreate', null);
                    }
                } else {
                    if (!$after) {
                        $after = isAke($this->_hooks, 'afterUpdate', null);
                    }
                }

                if ($after) {
                    call_user_func_array($after, [$row]);
                }

                return $row;
            }

            return false;
        }

        public function __set($k, $v)
        {
            return $this->set($k, $v);
        }

        public function __get($k)
        {
            return $this->get($k);
        }

        public function __isset($k)
        {
            return $this->has($k);
        }

        public function __unset($k)
        {
            return $this->del($k);
        }

        public function set($key, $value)
        {
            if (fnmatch('*_id', $key)) {
                if (is_numeric($value)) {
                    $value = (int) $value;
                } elseif (is_object($value)) {
                    $value = (int) $value->id;
                    $this->storage[str_replace('_id', '', $key)] = $value->toArray();
                }
            } else {
                if (is_object($value)) {
                    if ($value instanceof Time) {
                        $value = $value->timestamp;
                    } else {
                        $this->storage[$key . '_id'] = $value->id;
                        $value = $value->toArray();
                    }
                }
            }

            $method = lcfirst(Strings::camelize('set_' . $key . '_attribute'));

            $methods = get_class_methods($this);

            if (in_array($method, $methods)) {
                $value = $this->$method($value);
            }

            $this->storage[$key] = $value;

            $autosave = isAke($this->storage, 'autosave', false);

            return !$autosave ? $this : $this->save();
        }

        public function get($key, $d = null)
        {
            $method = lcfirst(Strings::camelize('get_' . $key . '_attribute'));

            $methods = get_class_methods($this);

            if (in_array($method, $methods)) {
                return $this->$method();
            }

            $value = isAke($this->storage, $key, false);

            if (false === $value) {
                if ($key[strlen($key) - 1] == 's' && isset($this->storage['id']) && $key[0] != '_') {
                    $db = $this->db()->instanciate($this->db()->db(), substr($key, 0, -1));

                    $idField = $this->db()->table() . '_id';

                    return $db->where([$idField, '=', $this->storage['id']])->get(null, true);
                } elseif (isset($this->storage[$key . '_id'])) {
                    $db = $this->db()->instanciate($this->db()->db(), $key);

                    return $db->find($this->storage[$key . '_id']);
                } else {
                    $value = $d;
                }
            }

            return $value;
        }

        public function has($key)
        {
            $method = lcfirst(Strings::camelize('isset_' . $key . '_attribute'));

            $methods = get_class_methods($this);

            if (in_array($method, $methods)) {
                return $this->$method();
            }

            $check = sha1(__file__ . time());

            return $check != isAke($this->storage, $key, $check);
        }

        public function del($key)
        {
            $method = lcfirst(Strings::camelize('unset_' . $key . '_attribute'));

            $methods = get_class_methods($this);

            if (in_array($method, $methods)) {
                return $this->$method();
            }

            unset($this->storage[$key]);

            return $this;
        }

        public function fill($data = [])
        {
            $data = empty($data) ? $_POST : $data;

            if (Arrays::isAssoc($data)) {
                foreach ($data as $k => $v) {
                    if ($k != 'id') {
                        if ('true' == $v) {
                            $v = true;
                        } elseif ('false' == $v) {
                            $v = false;
                        } elseif ('null' == $v) {
                            $v = null;
                        }

                        if (fnmatch('*_id', $k)) {
                            if (is_numeric($v)) {
                                $v = (int) $v;
                            } elseif (is_object($value)) {
                                $v = (int) $v->id;
                                $this->storage[str_replace('_id', '', $k)] = $v->toArray();
                            }
                        } else {
                            if (is_object($value)) {
                                $this->storage[$k . '_id'] = $v->id;
                                $value = $v->toArray();
                            }
                        }

                        $this->storage[$k] = $v;
                    }
                }
            }

            return $this;
        }

        public function populate($data = [])
        {
            foreach ($data as $k => $v) {
                $this->set($k, $v);
            }

            return $this;
        }

        public function hydrate($data = [])
        {
            return $this->fill($data);
        }

        public function incr($k, $by = 1)
        {
            $old = $this->get($k, 0);
            $new = $old + $by;

            $this->set($k, $new);

            return (int) $new;
        }

        public function increment($k, $by = 1)
        {
            return $this->incr($k, $by);
        }

        public function decr($k, $by = 1)
        {
            $old = $this->get($k, 1);
            $new = $old - $by;

            $this->set($k, $new);

            return (int) $new;
        }

        public function decrement($k, $by = 1)
        {
            return $this->decr($k, $by);
        }

        public function offsetSet($key, $value)
        {
            return $this->set($key, $value);
        }

        public function offsetGet($key)
        {
            return $this->get($key);
        }

        public function offsetExists($key)
        {
            return $this->has($key);
        }

        public function offsetUnset($key)
        {
            return $this->del($key);
        }

        public function toArray()
        {
            return $this->storage;
        }

        public function toJson()
        {
            return json_encode($this->storage);
        }

        public function __toString()
        {
            return $this->toJson();
        }

        public function __call($func, $args)
        {
            if ($db = $this->db()) {
                if (substr($func, 0, strlen('get')) == 'get') {
                    $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($func, strlen('get'))));
                    $field              = Strings::lower($uncamelizeMethod);

                    $method = lcfirst(Strings::camelize('get_' . $field . '_attribute'));

                    $methods = get_class_methods($this);

                    if (in_array($method, $methods)) {
                        return $this->$method();
                    }

                    $default = count($args) == 1 ? current($args) : null;

                    $res =  isAke($this->storage, $field, false);

                    if (false !== $res) {
                        return $res;
                    } else {
                        $resFk = isAke($this->storage, $field . '_id', false);

                        if (false !== $resFk) {
                            $db = $this->db()->instanciate($this->db()->db(), $field);
                            $object = count($args) == 1 ? $args[0] : false;

                            if (!is_bool($object)) {
                                $object = false;
                            }

                            return $db->find($resFk, $object);
                        } else {
                            if ($field[strlen($field) - 1] == 's' && isset($this->storage['id']) && $field[0] != '_') {
                                $db = $this->db()->instanciate($this->db()->db(), substr($field, 0, -1));
                                $object = count($args) == 1 ? $args[0] : false;

                                if (!is_bool($object)) {
                                    $object = false;
                                }

                                $hasPivot   = $this->hasPivot($db);

                                if (true === $hasPivot) {
                                    $model  = $db->model();
                                    $pivots = $this->pivots($model)->get();

                                    $ids = [];

                                    if (!empty($pivots)) {
                                        foreach ($pivots as $pivot) {
                                            $id = isAke($pivot, substr($field, 0, -1) . '_id', false);

                                            if (false !== $id) {
                                                array_push($ids, $id);
                                            }
                                        }

                                        if (!empty($ids)) {
                                            return $db->where(['id', 'IN', implode(',', $ids)])->get($object);
                                        } else {
                                            return [];
                                        }
                                    }
                                } else {
                                    $idField = $this->db()->table() . '_id';

                                    return $db->where([$idField, '=', $this->storage['id']])->get($object);
                                }
                            } else {
                                return $default;
                            }
                        }
                    }
                } elseif (substr($func, 0, strlen('has')) == 'has' && strlen($func) > strlen('has')) {
                    $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($func, strlen('has'))));
                    $field              = Strings::lower($uncamelizeMethod);

                    $res =  isAke($this->storage, $field, false);

                    if (false !== $res) {
                        return true;
                    } else {
                        $resFk = isAke($this->storage, $field . '_id', false);

                        if (false !== $resFk) {
                            return true;
                        } else {
                            if ($field[strlen($field) - 1] == 's' && isset($this->storage['id']) && $field[0] != '_') {
                                $db = $this->db()->instanciate($this->db()->db(), substr($field, 0, -1));

                                $hasPivot = $this->hasPivot($db);

                                if (true === $hasPivot) {
                                    $model  = $db->model();
                                    $pivots = $this->pivots($model)->get();

                                    $ids = [];

                                    if (!empty($pivots)) {
                                        foreach ($pivots as $pivot) {
                                            $id = isAke($pivot, substr($field, 0, -1) . '_id', false);

                                            if (false !== $id) {
                                                return true;
                                            }
                                        }

                                        return false;
                                    }
                                } else {
                                    $idField = $this->db()->table() . '_id';

                                    $count = $db->where([$idField, '=', $this->storage['id']])->count();

                                    return $count > 0 ? true : false;
                                }
                            }
                        }
                    }

                    return false;
                } elseif (substr($func, 0, strlen('belongsTo')) == 'belongsTo' && strlen($func) > strlen('belongsTo')) {
                    $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($func, strlen('set'))));
                    $field              = Strings::lower($uncamelizeMethod);

                    $fk = current($args);

                    if (is_object($fk)) {
                        $val = isAke($this->storage, $field . '_id', false);
                        $fkId = isset($fk->id) ? $fk->id : false;

                        if ($val && $fkId) {
                            return intval($val) == intval($fkId);
                        }
                    }

                    return false;
                } elseif (substr($func, 0, strlen('belongsToMany')) == 'belongsToMany' && strlen($func) > strlen('belongsToMany')) {
                    $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($func, strlen('set'))));
                    $field              = Strings::lower($uncamelizeMethod);

                    if (is_object($fk)) {
                        return $this->belongsToMany($field);
                    }

                    return false;
                } elseif (substr($func, 0, strlen('set')) == 'set') {
                    $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($func, strlen('set'))));
                    $field              = Strings::lower($uncamelizeMethod);

                    if (!empty($args)) {
                        $val = current($args);
                    } else {
                        $val = null;
                    }

                    if (fnmatch('*_id', $field)) {
                        if (is_numeric($val)) {
                            $val = (int) $val;
                        } elseif (is_object($val)) {
                            $val = (int) $val->id;
                            $this->storage[str_replace('_id', '', $field)] = $val->toArray();
                        }
                    } else {
                        if (is_object($val)) {
                            if ($val instanceof TimeLib) {
                                $val = $val->timestamp;
                            } else {
                                $this->storage[$field . '_id'] = $val->id;
                                $val = $val->toArray();
                            }
                        }
                    }

                    $method = lcfirst(Strings::camelize('set_' . $field . '_attribute'));

                    $methods = get_class_methods($this);

                    if (in_array($method, $methods)) {
                        $val = $this->$method($val);
                    }

                    $this->storage[$field] = $val;

                    $autosave = isAke($this->storage, 'autosave', false);

                    return !$autosave ? $this : $this->save();
                } else {
                    $cb = isAke($this->_events, $func, false);

                    if (false !== $cb) {
                        if ($cb instanceof Closure) {
                            return call_user_func_array($cb, $args);
                        }
                    } else {
                        if ($func[strlen($func) - 1] == 's' && isset($this->storage['id']) && $func[0] != '_') {
                            $db     = $this->db()->instanciate($this->db()->db(), substr($func, 0, -1));
                            $object = count($args) == 1 ? $args[0] : false;

                            if (!is_bool($object)) {
                                $object = false;
                            }

                            $hasPivot   = $this->hasPivot($db);

                            if (true === $hasPivot) {
                                $model  = $db->model();
                                $pivots = $this->pivots($model)->get();

                                $ids = [];

                                if (!empty($pivots)) {
                                    foreach ($pivots as $pivot) {
                                        $id = isAke($pivot, substr($func, 0, -1) . '_id', false);

                                        if (false !== $id) {
                                            array_push($ids, $id);
                                        }
                                    }

                                    if (!empty($ids)) {
                                        return $db->where(['id', 'IN', implode(',', $ids)])->get($object);
                                    } else {
                                        return [];
                                    }
                                }
                            } else {
                                $idField = $this->db()->table() . '_id';

                                return $db->where([$idField, '=', $this->storage['id']])->toArray($object);
                            }
                        } else {
                            if (!empty($args)) {
                                $object = count($args) == 1 ? $args[0] : false;
                                $db = $this->db()->instanciate($this->db()->db(), $func);

                                $field = $func . '_id';

                                if (is_bool($object) && isset($this->storage[$field])) {
                                    return $db->find($value, $object);
                                } elseif (is_object($object)) {
                                    $this->$field = (int) $object->id;

                                    return $this;
                                }
                            }

                            $auth = ['checkIndices', '_hooks', 'rel', 'boot'];

                            if (in_array($func, $auth)) {
                                return true;
                            }

                            if (is_callable($this->$func)) {
                                $args = array_merge([$this], $args);

                                return call_user_func_array($this->$func, $args);
                            }

                            exception('Model', "$func is not a model function of " . $this->db()->db());
                        }
                    }
                }
            } else {
                $k = Inflector::uncamelize(substr($func, 3));

                if (fnmatch('get*', $func)) {
                    $default = empty($args) ? null : current($args);

                    return $this->get($k, $default);
                } elseif (fnmatch('set*', $func)) {
                    return $this->set($k, current($args));
                } elseif (fnmatch('has*', $func)) {
                    return $this->has($k);
                } else {
                    return $this->set($func, current($args));
                }
            }
        }

        private function treatCast($tab)
        {
            if (!empty($tab) && Arrays::isAssoc($tab)) {
                foreach ($tab as $k => $v) {
                    if (fnmatch('*_id', $k) && !empty($v)) {
                        if (is_numeric($v)) {
                            $tab[$k] = (int) $v;
                        }
                    }
                }
            }

            return $tab;
        }

        public function _keys()
        {
            return array_keys($this->storage);
        }

        public function expurge($field)
        {
            unset($this->storage[$field]);

            return $this;
        }

        public function _related()
        {
            $fields = array_keys($this->storage);
            $obj = $this;

            foreach ($fields as $field) {
                if (fnmatch('*_id', $field)) {
                    if (is_string($field)) {
                        $value = $this->$field;

                        if (!is_callable($value) && $this->db) {
                            $fk = str_replace('_id', '', $field);
                            $ns = $this->db()->db();
                            $i  = $this->db();

                            $cb = function($object = false) use ($i, $value, $fk, $ns, $field, $obj) {
                                $db = $i->instanciate($ns, $fk);

                                if (is_bool($object)) {
                                    return $db->find($value, $object);
                                } elseif (is_object($object)) {
                                    $obj->$field = (int) $object->id;

                                    return $obj;
                                }
                            };

                            $this->_event($fk, $cb);
                        }
                    }
                }
            }

            return $this;
        }

        public function _event($name, Closure $cb)
        {
            $this->_events[$name] = $cb;

            return $this;
        }

        public function id()
        {
            return isAke($this->storage, 'id', null);
        }

        public function exists()
        {
            return null !== isAke($this->storage, 'id', null);
        }

        public function duplicate()
        {
            $this->storage['copyrow'] = sha1(__file__ . time());

            unset($this->storage['id']);
            unset($this->storage['created_at']);
            unset($this->storage['updated_at']);
            unset($this->storage['deleted_at']);

            return $this->save();
        }

        public function assoc()
        {
            return $this->storage;
        }

        public function __invoke($json = false)
        {
            return $json ? $this->save()->toJson() : $this->save();
        }

        public function deleteSoft()
        {
            $this->storage['deleted_at'] = time();

            return $this->save();
        }

        public function with($model)
        {
            $db = $model->db()->db();
            $table = $model->db()->table();

            if ($db == $this->db()->db()) {
                $this->storage[$table . '_id'] = $model->id;
                $this->storage[$table] = $model->toArray();
            } else {
                $this->storage[$db . '_' . $table . '_id'] = $model->id;
                $this->storage[$db . '_' . $table] = $model->toArray();
            }

            return $this;
        }

        public function attach($model, $attributes = [])
        {
            $m = !is_array($model) ? $model : current($model);

            if (!isset($this->storage['id']) || empty($m->id)) {
                exception('Model', "Attach method requires a valid model.");
            }

            $mTable = $m->db()->table();

            $names = [$this->db()->table(), $mTable];
            asort($names);
            $pivot = Strings::lower('pivot' . implode('', $names));

            $db = $this->db()->instanciate($this->db()->db(), $pivot);

            if (is_array($model)) {
                foreach ($model as $mod) {
                    $id = (int) $mod->id;

                    $row = $mod->db()->find($id);

                    if ($row) {
                        $fieldAttach    = $mTable . '_id';
                        $fieldModel     = $this->db()->table() . '_id';

                        $attach = $db->firstOrCreate([
                            $fieldAttach    => $id,
                            $fieldModel     => $this->storage['id']
                        ]);

                        if (!empty($attributes)) {
                            foreach ($attributes as $k => $v) {
                                $setter = setter($k);
                                $attach->$setter($v);
                            }

                            $attach->save();
                        }
                    }
                }
            } else {
                $id = (int) $model->id;
                $row = $model->db()->find($id);

                if ($row) {
                    $fieldAttach    = $mTable . '_id';
                    $fieldModel     = $this->db()->table() . '_id';

                    $attach = $db->firstOrCreate([
                        $fieldAttach    => $id,
                        $fieldModel     => $this->storage['id']
                    ]);

                    if (!empty($attributes)) {
                        foreach ($attributes as $k => $v) {
                            $setter = setter($k);
                            $attach->$setter($v);
                        }

                        $attach->save();
                    }
                }
            }

            return $this;
        }

        public function detach($model)
        {
            if (!isset($this->storage['id'])) {
                exception('Model', "detach method requires a valid model.");
            }

            $m = !is_array($model) ? $model : current($model);

            if (is_object($m)) {
                $m = $m->model();
            }

            $all = false;

            if (empty($m->id)) {
                $all = true;
            }

            $mTable = $m->db()->table();

            $names = [$this->db()->table(), $mTable];
            asort($names);
            $pivot = Strings::lower('pivot' . implode('', $names));

            $db = $this->db()->instanciate($this->db()->db(), $pivot);

            if (is_array($model)) {
                foreach ($model as $mod) {
                    $id = (int) $mod->id;

                    $row = $mod->db()->find($id);

                    if ($row) {
                        $fieldAttach    = $mTable . '_id';
                        $fieldModel     = $this->db()->table() . '_id';

                        $attach = $db->where([$fieldAttach , '=', (int) $id])
                        ->where([$fieldModel, '=', (int) $this->storage['id']])
                        ->first(true);

                        if ($attach) {
                            $attach->delete();
                        }
                    }
                }
            } else {
                if (false === $all) {
                    $id = (int) $model->id;
                    $row = $model->db()->find($id);

                    if ($row) {
                        $fieldAttach    = $mTable . '_id';
                        $fieldModel     = $this->db()->table() . '_id';

                        $attach = $db->where([$fieldAttach , '=', (int) $id])
                        ->where([$fieldModel, '=', (int) $this->storage['id']])
                        ->first(true);

                        if ($attach) {
                            $attach->delete();
                        }
                    }
                } else {
                    $fieldModel = $this->db()->table() . '_id';

                    $attachs = $db->where([$fieldModel, '=', (int) $this->storage['id']])
                    ->get(true);

                    if (!empty($attachs)) {
                        foreach ($attachs as $attach) {
                            $attach->delete();
                        }
                    }
                }
            }

            return $this;
        }

        public function actual()
        {
            return $this;
        }

        public function initial($model = false)
        {
            return $model ? new self($this->db(), $this->initial) : $this->initial;
        }

        public function cancel()
        {
            $this->storage = $this->initial;

            return $this;
        }

        public function isDirty()
        {
            return sha1(serialize($this->storage)) != sha1(serialize($this->initial));
        }

        public function take($fk)
        {
            if (!isset($this->storage['id'])) {
                exception('Model', 'id must be defined to use take.');
            }

            $db = fnmatch('*s', $fk) ? $this->db()->instanciate($this->db()->db(), substr($fk, 0, -1)) : $this->db()->instanciate($this->db()->db(), $fk);

            return $db->where([$this->db()->table() . '_id', '=', (int) $this->storage['id']]);
        }

        public function through($t1, $t2)
        {
            $database = $this->db()->db();

            $db1 = $this->db()->instanciate($database, $t1);

            $fk = $this->db()->table() . '_id';

            $sub = $db1->where([$fk, '=', (int) $this->storage['id']])->get();

            $ids = [];

            foreach ($sub as $row) {
                $ids[] = $row['id'];
            }

            $fk2 = $t1 . '_id';

            return $this->db()->instanciate($database, $t2)->where([$fk2, 'IN', implode(',', $ids)])->get()->toArray();
        }

        public function hasThrough($t1, $t2)
        {
            $database = $this->db()->db();

            $db1 = $this->db()->instanciate($database, $t1);

            $fk = $this->db()->table() . '_id';

            $sub = $db1->where([$fk, '=', (int) $this->_data['id']])->get();

            $ids = [];

            foreach ($sub as $row) {
                $ids[] = $row['id'];
            }

            $fk2 = $t1 . '_id';

            return $this->db()->instanciate($database, $t2)->where([$fk2, 'IN', implode(',', $ids)])->count() > 0 ? true : false;
        }

        public function countThrough($t1, $t2)
        {
            $database = $this->db()->db();

            $db1 = $this->db()->instanciate($database, $t1);

            $fk = $this->db()->table() . '_id';

            $sub = $db1->where([$fk, '=', (int) $this->_data['id']])->get();

            $ids = [];

            foreach ($sub as $row) {
                $ids[] = $row['id'];
            }

            $fk2 = $t1 . '_id';

            return $this->db()->instanciate($database, $t2)->where([$fk2, 'IN', implode(',', $ids)])->count();
        }

        public function create(array $data)
        {
            return new self($this->db(), $data);
        }

        public function createFromId($id)
        {
            $row = $this->db()->find($id);

            if ($row) {
                $row = $row->toArray();
                unset($row['id']);
                unset($row['created_at']);
                unset($row['updated_at']);

                return (new self($this->db(), $row))->save();
            }

            return $this;
        }

        public function fillAndSave(array $data)
        {
            return $this->fill($data)->save();
        }

        public function timestamps()
        {
            return [
                'created_at' => (new Time)->createFromTimestamp(isAke($this->storage, 'created_at', time())),
                'updated_at' => (new Time)->createFromTimestamp(isAke($this->storage, 'updated_at', time()))
            ];
        }

        public function updated()
        {
            return (new Time)->createFromTimestamp(isAke($this->storage, 'updated_at', time()));
        }

        public function created()
        {
            return (new Time)->createFromTimestamp(isAke($this->storage, 'updated_at', time()));
        }

        public function __destruct()
        {
            $methods = get_class_methods($this);

            if (in_array('autosave', $methods)) {
                $autosave = $this->autosave();

                if ($autosave) {
                    $this->save();
                }
            }
        }

        public function touch()
        {
            $this->storage['updated_at'] = time();

            return $this->save();
        }

        public function associate($model)
        {
            $db = $model->db();
            $field = $db->table . '_id';

            $this->storage[$field] = $model->id;
            $this->storage[$db->table] = $model->toArray();

            return $this;
        }

        public function dissociate($model)
        {
            $db = $model->db();
            $field = $db->table . '_id';

            unset($this->storage[$field]);
            unset($this->storage[$db->table]);

            return $this;
        }

        public function related()
        {
            $fields = func_get_args();

            foreach ($fields as $field) {
                if (fnmatch('*_*', $field)) {
                    list($db, $table) = explode('_', $field, 2);
                } else {
                    $table = $field;
                    $db = SITE_NAME;
                }

                $fid = isAke($this->storage, $field . '_id', false);

                if ($fid) {
                    $row = $this->db()->instanciate($db, $table)->find((int) $fid, false);

                    $this->storage[$key] = $row;
                }
            }

            return $this;
        }

        public function custom(callable $closure)
        {
            return call_user_func_array($closure, [$this]);
        }

        public function createFromQuery($query)
        {
            if (!empty($query)) {
                $rows = $this->db()->where($query);

                if ($rows->count() > 0) {
                    foreach ($rows->get() as $row) {
                        unset($row['id']);
                        unset($row['created_at']);
                        unset($row['updated_at']);

                        $new = (new self($this->db(), $row))->save();
                    }
                }
            }

            return $this;
        }

        public function pivot($model)
        {
            if (is_object($model)) {
                $model = $model->model();
            }

            $mTable = $model->db()->table();

            $names = [$this->db()->table(), $mTable];

            asort($names);

            $pivot = Strings::lower('pivot' . implode('', $names));

            return $this->db()->instanciate($this->db()->db(), $pivot);
        }

        public function pivots($model)
        {
            if (!isset($this->storage['id'])) {
                exception('Model', "pivots method requires a valid model.");
            }

            $fieldModel = $this->db()->table() . '_id';

            return $this->pivot($model)->where([$fieldModel, '=', (int) $this->storage['id']]);
        }

        public function hasPivot($model)
        {
            if (!isset($this->storage['id'])) {
                exception('Model', "hasPivot method requires a valid model.");
            }

            if (is_object($model)) {
                $model = $model->model();
            }

            $fieldModel = $this->db()->table() . '_id';

            $count = $this->pivot($model)->where([$fieldModel, '=', (int) $this->storage['id']])->count();

            return $count > 0 ? true : false;
        }

        public function getPivots($pivot)
        {
            return $this->getPivot($pivot, false);
        }

        public function getPivot($pivot, $first = true, $model = false)
        {
            $res = $this->pivots($pivot);

            return $first ? $res->first($model) : $res->get($model);
        }

        public function oneToOne($table)
        {
            if (!isset($this->storage['id'])) {
                exception('Model', "oneToOne method requires a valid model.");
            }

            $idFk = $table . '_id';

            if (!isset($this->storage[$idFk])) {
                exception('Model', "oneToOne method requires a valid model.");
            }

            $rowFk = $this->db()->instanciate($this->db()->db(), $table)->find($this->storage[$idFk]);

            return $rowFk ? true : false;
        }

        public function belongsToOne($table)
        {
            if (!isset($this->storage['id'])) {
                exception('Model', "belongsToOne method requires a valid model.");
            }

            $idFk = $table . '_id';

            if (!isset($this->storage[$idFk])) {
                exception('Model', "belongsToOne method requires a valid model.");
            }

            $rowFk = $this->db()->instanciate($this->db()->db(), $table)->find($this->storage[$idFk]);

            return $rowFk ? true : false;
        }

        public function belongsToMany($table)
        {
            $model = $this->db()->instanciate($this->db()->db(), $table)->model();

            $pivot = $this->pivot($model);

            return $this->getPivot($pivot, false)->count() > 0;
        }

        public function manyToMany($table)
        {
            $model = $this->db()->instanciate($this->db()->db(), $table)->model();

            $pivot = $this->pivot($model);

            return $this->getPivot($pivot, false);
        }

        public function oneToMany($table)
        {
            if (!isset($this->_data['id'])) {
                exception('Model', "belongsToOne method requires a valid model.");
            }

            $idFk = $this->db()->table() . '_id';

            $dbFk = $this->db()->instanciate($this->db()->db(), $table);

            return $dbFk->where([$idFk, '=', $this->storage['id']])->get();
        }

        public function check($cb = null)
        {
            $check = true;

            if (!$cb) {
                $cb = isAke($this->_hooks, 'validate', null);
            }

            if ($cb) {
                $check = call_user_func_array($cb, [$this->storage]);
            }

            return $check;
        }

        public static function __callStatic($method, $args)
        {
            $driver = Config::get('octalia.driver', 'sql') == 'sql' ? 'ldb' : 'odb';

            $db = Strings::uncamelize($method);

            if (fnmatch('*_*', $db)) {
                list($database, $table) = explode('_', $db, 2);
            } else {
                $database   = Strings::uncamelize(Config::get('application.name', 'core'));
                $table      = $db;
            }

            if (empty($args)) {
                return lib('octalia', [$database, $table, lib('cachelite', ["$database.$table"])]);
            } elseif (count($args) == 1) {
                $id = array_shift($args);

                if (is_numeric($id)) {
                    return lib('octalia', [$database, $table, lib('cachelite', ["$database.$table"])])
                    ->find($id);
                }
            }
        }
    }
