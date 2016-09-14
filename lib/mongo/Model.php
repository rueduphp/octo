<?php
    namespace Octo\Mongo;

    use Octo\File;
    use Octo\Arrays;
    use Octo\Inflector;
    use Octo\Exception;
    use Closure;
    use ArrayObject;
    use ArrayAccess;
    use Countable;
    use IteratorAggregate;

    class Model extends ArrayObject implements ArrayAccess, Countable, IteratorAggregate
    {
        const TYPE_INT          = 1;
        const TYPE_BOOL         = 2;
        const TYPE_STRING       = 3;
        const TYPE_FLOAT        = 4;
        const TYPE_DATE         = 5;
        const TYPE_HTML         = 6;
        const TYPE_NOTHING      = 7;
        const TYPE_SQL          = 8;
        const TYPE_TIMESTAMP    = 9;
        const TYPE_EMPTY        = 10;
        const TYPE_TEXT         = 11;
        const TYPE_OBJECT       = 12;
        const TYPE_JSON         = 13;
        const TYPE_CACHE        = 14;
        const TYPE_TMP          = 15;
        const TYPE_FOREIGN_KEY  = 16;
        const TYPE_PRIMARY_KEY  = 17;
        const TYPE_INDEX_KEY    = 18;

        public $_db, $_initial;
        public $_data = [];
        public $_events = [];
        public $_hooks = [
            'beforeCreate'  => null,
            'beforeRead'    => null,
            'beforeUpdate'  => null,
            'beforeDelete'  => null,
            'afterCreate'   => null,
            'afterRead'     => null,
            'afterUpdate'   => null,
            'afterDelete'   => null,
            'validate'      => null
        ];

        public function __construct(Db $db, $data = [])
        {
            $this->_db  = $db;
            $data       = $this->treatCast($data);

            $id = isAke($data, 'id', false);

            if (false !== $id) {
                $this->_data['id'] = (int) $id;

                unset($data['id']);
            }

            if (!is_array($data)) {
                $data = [];
            }

            $this->_data = array_merge($this->_data, $data);

            $this->boot();

            if (false !== $id) {
                $this->_related();
            }

            $this->_hooks();

            $this->_initial = $this->assoc();
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
            return array_keys($this->_data);
        }

        public function expurge($field)
        {
            unset($this->_data[$field]);

            return $this;
        }

        public function _related()
        {
            $fields = array_keys($this->_data);
            $obj = $this;

            foreach ($fields as $field) {
                if (fnmatch('*_id', $field)) {
                    if (is_string($field)) {
                        $value = $this->$field;

                        if (!is_callable($value)) {
                            $fk = str_replace('_id', '', $field);
                            $ns = $this->_db->db;

                            $cb = function($object = false) use ($value, $fk, $ns, $field, $obj) {
                                $db = Db::instance($ns, $fk);

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

        public function offsetSet($key, $value)
        {
            if (fnmatch('*_id', $key)) {
                if (is_numeric($value)) {
                    $value = (int) $value;
                } elseif (is_object($value)) {
                    $value = (int) $value->id;
                    $this->_data[str_replace('_id', '', $key)] = $value->toArray();
                }
            } else {
                if (is_object($value)) {
                    if ($value instanceof \Thin\TimeLib) {
                        $value = $value->timestamp;
                    } else {
                        $this->_data[$key . '_id'] = $value->id;
                        $value = $value->toArray();
                    }
                }
            }

            $method = lcfirst(Inflector::camelize('set_' . $key . '_attribute'));

            $methods = get_class_methods($this);

            if (in_array($method, $methods)) {
                $value = $this->$method($value);
            }

            $this->_data[$key] = $value;

            $autosave = isAke($this->_data, 'autosave', false);

            return !$autosave ? $this : $this->save();
        }

        public function offsetExists($key)
        {
            $method = lcfirst(Inflector::camelize('isset_' . $key . '_attribute'));

            $methods = get_class_methods($this);

            if (in_array($method, $methods)) {
                return $this->$method();
            }

            $check = uuid();

            return $check != isAke($this->_data, $key, $check);
        }

        public function offsetUnset($key)
        {
            $method = lcfirst(Inflector::camelize('unset_' . $key . '_attribute'));

            $methods = get_class_methods($this);

            if (in_array($method, $methods)) {
                return $this->$method();
            }

            unset($this->_data[$key]);

            return $this;
        }

        public function offsetGet($key)
        {
            $method = lcfirst(Inflector::camelize('get_' . $key . '_attribute'));

            $methods = get_class_methods($this);

            if (in_array($method, $methods)) {
                return $this->$method();
            }

            $value = isAke($this->_data, $key, false);

            if (false === $value) {
                if ($key[strlen($key) - 1] == 's' && isset($this->_data['id']) && $key[0] != '_') {
                    $db = Db::instance($this->_db->db, substr($key, 0, -1));

                    $idField = $this->_db->table . '_id';

                    return $db->where([$idField, '=', $this->_data['id']])->exec(true);
                } elseif (isset($this->_data[$key . '_id'])) {
                    $db = Db::instance($this->_db->db, $key);

                    return $db->find($this->_data[$key . '_id']);
                } else {
                    $value = null;
                }
            }

            return $value;
        }

        public function __set($key, $value)
        {
            if (fnmatch('*_id', $key)) {
                if (is_numeric($value)) {
                    $value = (int) $value;
                } elseif (is_object($value)) {
                    $value = (int) $value->id;
                    $this->_data[str_replace('_id', '', $key)] = $value->toArray();
                }
            } else {
                if (is_object($value) && !is_callable($value)) {
                    if ($value instanceof \Thin\TimeLib) {
                        $value = $value->timestamp;
                    } else {
                        $this->_data[$key . '_id'] = $value->id;
                        $value = $value->toArray();
                    }
                }
            }

            $method = lcfirst(Inflector::camelize('set_' . $key . '_attribute'));

            $methods = get_class_methods($this);

            if (in_array($method, $methods)) {
                $value = $this->$method($value);
            }

            $this->_data[$key] = $value;

            $autosave = isAke($this->_data, 'autosave', false);

            return !$autosave ? $this : $this->save();
        }

        public function __get($key)
        {
            $method = lcfirst(Inflector::camelize('get_' . $key . '_attribute'));

            $methods = get_class_methods($this);

            if (in_array($method, $methods)) {
                return $this->$method();
            }

            $value = isAke($this->_data, $key, false);

            if (false === $value) {
                if ($key[strlen($key) - 1] == 's' && isset($this->_data['id']) && $key[0] != '_') {
                    $db         = Db::instance($this->_db->db, substr($key, 0, -1));
                    $hasPivot   = $this->hasPivot($db);

                    if (true === $hasPivot) {
                        $model  = $db->model();
                        $pivots = $this->pivots($model)->exec();

                        $ids = [];

                        if (!empty($pivots)) {
                            foreach ($pivots as $pivot) {
                                $id = isAke($pivot, substr($key, 0, -1) . '_id', false);

                                if (false !== $id) {
                                    array_push($ids, $id);
                                }
                            }

                            if (!empty($ids)) {
                                return $db->where(['id', 'IN', implode(',', $ids)])->exec(true);
                            } else {
                                return [];
                            }
                        }
                    } else {
                        $idField = $this->_db->table . '_id';

                        return $db->where([$idField, '=', $this->_data['id']])->exec(true);
                    }
                } elseif (isset($this->_data[$key . '_id'])) {
                    $db = Db::instance($this->_db->db, $key);

                    return $db->find($this->_data[$key . '_id']);
                } else {
                    $value = null;
                }
            }

            return $value;
        }

        public function __isset($key)
        {
            $method = lcfirst(Inflector::camelize('isset_' . $key . '_attribute'));

            $methods = get_class_methods($this);

            if (in_array($method, $methods)) {
                return $this->$method();
            }

            $check = sha1(__file__);

            return $check != isAke($this->_data, $key, $check);
        }

        public function __unset($key)
        {
            $method = lcfirst(Inflector::camelize('unset_' . $key . '_attribute'));

            $methods = get_class_methods($this);

            if (in_array($method, $methods)) {
                return $this->$method();
            }

            unset($this->_data[$key]);
        }

        public function __call($func, $args)
        {
            if (substr($func, 0, strlen('get')) == 'get') {
                $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($func, strlen('get'))));
                $field              = Inflector::lower($uncamelizeMethod);

                $method = lcfirst(Inflector::camelize('get_' . $field . '_attribute'));

                $methods = get_class_methods($this);

                if (in_array($method, $methods)) {
                    return $this->$method();
                }

                $default = count($args) == 1 ? Arrays::first($args) : null;

                $res =  isAke($this->_data, $field, false);

                if (false !== $res) {
                    return $res;
                } else {
                    $resFk = isAke($this->_data, $field . '_id', false);

                    if (false !== $resFk) {
                        $db = Db::instance($this->_db->db, $field);
                        $object = count($args) == 1 ? $args[0] : false;

                        if (!is_bool($object)) {
                            $object = false;
                        }

                        return $db->find($resFk, $object);
                    } else {
                        if ($field[strlen($field) - 1] == 's' && isset($this->_data['id']) && $field[0] != '_') {
                            $db = Db::instance($this->_db->db, substr($field, 0, -1));
                            $object = count($args) == 1 ? $args[0] : false;

                            if (!is_bool($object)) {
                                $object = false;
                            }

                            $hasPivot   = $this->hasPivot($db);

                            if (true === $hasPivot) {
                                $model  = $db->model();
                                $pivots = $this->pivots($model)->exec();

                                $ids = [];

                                if (!empty($pivots)) {
                                    foreach ($pivots as $pivot) {
                                        $id = isAke($pivot, substr($field, 0, -1) . '_id', false);

                                        if (false !== $id) {
                                            array_push($ids, $id);
                                        }
                                    }

                                    if (!empty($ids)) {
                                        return $db->where(['id', 'IN', implode(',', $ids)])->exec($object);
                                    } else {
                                        return [];
                                    }
                                }
                            } else {
                                $idField = $this->_db->table . '_id';

                                return $db->where([$idField, '=', $this->_data['id']])->exec($object);
                            }
                        } else {
                            return $default;
                        }
                    }
                }
            } elseif (substr($func, 0, strlen('has')) == 'has') {
                $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($func, strlen('has'))));
                $field              = Inflector::lower($uncamelizeMethod);

                $res =  isAke($this->_data, $field, false);

                if (false !== $res) {
                    return true;
                } else {
                    $resFk = isAke($this->_data, $field . '_id', false);

                    if (false !== $resFk) {
                        return true;
                    } else {
                        if ($field[strlen($field) - 1] == 's' && isset($this->_data['id']) && $field[0] != '_') {
                            $db = Db::instance($this->_db->db, substr($field, 0, -1));

                            $hasPivot = $this->hasPivot($db);

                            if (true === $hasPivot) {
                                $model  = $db->model();
                                $pivots = $this->pivots($model)->exec();

                                $ids = [];

                                if (!empty($pivots)) {
                                    foreach ($pivots as $pivot) {
                                        $id = isAke($pivot, substr($field, 0, -1) . '_id', false);

                                        if (false !== $id) {
                                            array_push($ids, $id);
                                        }
                                    }

                                    return !empty($ids) ? true : false;
                                }
                            } else {
                                $idField = $this->_db->table . '_id';

                                $count = $db->where([$idField, '=', $this->_data['id']])->count();

                                return $count > 0 ? true : false;
                            }
                        }
                    }
                }

                return false;
            } elseif (substr($func, 0, strlen('set')) == 'set') {
                $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($func, strlen('set'))));
                $field              = Inflector::lower($uncamelizeMethod);

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
                        $this->_data[str_replace('_id', '', $field)] = $val->toArray();
                    }
                } else {
                    if (is_object($val)) {
                        if ($val instanceof \Thin\TimeLib) {
                            $val = $val->timestamp;
                        } else {
                            $this->_data[$field . '_id'] = $val->id;
                            $val = $val->toArray();
                        }
                    }
                }

                $method = lcfirst(Inflector::camelize('set_' . $field . '_attribute'));

                $methods = get_class_methods($this);

                if (in_array($method, $methods)) {
                    $val = $this->$method($val);
                }

                $this->_data[$field] = $val;

                $autosave = isAke($this->_data, 'autosave', false);

                return !$autosave ? $this : $this->save();
            } else {
                $cb = isAke($this->_events, $func, false);

                if (false !== $cb) {
                    if ($cb instanceof Closure) {
                        return call_user_func_array($cb, $args);
                    }
                } else {
                    if ($func[strlen($func) - 1] == 's' && isset($this->_data['id']) && $func[0] != '_') {
                        $db     = Db::instance($this->_db->db, substr($func, 0, -1));
                        $object = count($args) == 1 ? $args[0] : false;

                        if (!is_bool($object)) {
                            $object = false;
                        }

                        $hasPivot   = $this->hasPivot($db);

                        if (true === $hasPivot) {
                            $model  = $db->model();
                            $pivots = $this->pivots($model)->exec();

                            $ids = [];

                            if (!empty($pivots)) {
                                foreach ($pivots as $pivot) {
                                    $id = isAke($pivot, substr($func, 0, -1) . '_id', false);

                                    if (false !== $id) {
                                        array_push($ids, $id);
                                    }
                                }

                                if (!empty($ids)) {
                                    return $db->where(['id', 'IN', implode(',', $ids)])->exec($object);
                                } else {
                                    return [];
                                }
                            }
                        } else {
                            $idField = $this->_db->table . '_id';

                            return $db->where([$idField, '=', $this->_data['id']])->exec($object);
                        }
                    } else {
                        if (!empty($args)) {
                            $object = count($args) == 1 ? $args[0] : false;
                            $db = Db::instance($this->_db->db, $func);

                            $field = $func . '_id';

                            if (is_bool($object) && isset($this->_data[$field])) {
                                return $db->find($value, $object);
                            } elseif (is_object($object)) {
                                $this->$field = (int) $object->id;

                                return $this;
                            }
                        }

                        if (is_callable($this->$func)) {
                            $args = array_merge([$this], $args);

                            return call_user_func_array($this->$func, $args);
                        }

                        $auth = ['checkIndices', '_hooks', 'rel', 'boot'];

                        if (Arrays::in($func, $auth)) {
                            return true;
                        }

                        throw new Exception("$func is not a model function of $this->_db.");
                    }
                }
            }
        }

        public function glue($field)
        {
            $one = isAke($this->_data, $field . '_id', false);

            if ($one) {
                $data = Db::instance($this->_db->db, $field)->find((int) $one, false);
                $this->_data[$field] = $data;
            } else {
                if ($field[strlen($field) - 1] == 's' && isset($this->_data['id']) && $field[0] != '_') {
                    $db = Db::instance($this->_db->db, substr($field, 0, -1));

                    $idField = $this->_db->table . '_id';

                    $this->_data[$field] = $db->where([$idField, '=', (int) $this->_data['id']])->cursor()->toArray();
                }
            }

            return $this;
        }

        public function save()
        {
            $valid  = true;
            $create = false;
            $id     = isAke($this->_data, 'id', false);

            if (false !== $id) {
                $continue = sha1(serialize($this->_data)) != sha1(serialize($this->initial()));

                if (false === $continue) {
                    return $this;
                }
            }

            $hook = isAke($this->_hooks, 'validate', false);

            if ($hook) {
                $valid = call_user_func_array($hook, [$this->_data]);
            }

            if (true !== $valid) {
                throw new Exception("This model must be valid to be saved.");
            }

            if ($id) {
                $hook   = isAke($this->_hooks, 'beforeUpdate', false);
            } else {
                $create = true;
                $hook   = isAke($this->_hooks, 'beforeCreate', false);
            }

            if ($hook) {
                call_user_func_array($hook, [$this]);
            }

            $row = $this->_db->save($this->_data);

            if ($create) {
                $hook = isAke($this->_hooks, 'afterCreate', false);
            } else {
                $hook = isAke($this->_hooks, 'afterUpdate', false);
            }

            if ($hook) {
                call_user_func_array($hook, [$row]);
            }

            return $row;
        }

        public function saveAndAttach($model, $atts = [])
        {
            $this->save();

            return $this->attach($model, $atts);
        }

        public function restore()
        {
            $id = isAke($this->_data, 'id', false);

            if (false !== $id) {
                $hook = isAke($this->_hooks, 'beforeRestore', false);

                if ($hook) {
                    call_user_func_array($hook, [$this]);
                }

                $row = $this->_db->save($this->_data);

                $hook = isAke($this->_hooks, 'afterRestore', false);

                if ($hook) {
                    call_user_func_array($hook, [$row]);
                }

                return $row;
            }

            return false;
        }

        public function insert()
        {
            $valid = true;

            $hook = isAke($this->_hooks, 'validate', false);

            if ($hook) {
                $valid = call_user_func_array($hook, [$this]);
            }

            if (true !== $valid) {
                throw new Exception("Thos model must be valid to be saved.");
            }

            $hook = isAke($this->_hooks, 'beforeCreate', false);

            if ($hook) {
                call_user_func_array($hook, [$this]);
            }

            $row = $this->_db->insert($this->_data);

            $hook = isAke($this->_hooks, 'afterCreate', false);

            if ($hook) {
                call_user_func_array($hook, [$row]);
            }

            return $row;
        }

        public function delete()
        {
            $id = isAke($this->_data, 'id', false);

            if (false !== $id) {
                $hook = isAke($this->_hooks, 'beforeDelete', false);

                if ($hook) {
                    call_user_func_array($hook, [$this]);
                }

                $res = $this->_db->delete((int) $id);

                $hook = isAke($this->_hooks, 'afterDelete', false);

                if ($hook) {
                    call_user_func_array($hook, [$this]);
                }

                return $res;
            }

            return false;
        }

        function deleteCascade(array $fields)
        {
            foreach ($fields as $field) {
                $val = isake($this->_data, $field, false);

                if (fnmatch('*_id', $field) && false !== $val) {
                    $row = bigDb(str_replace('_id', '', $field))->find($val);

                    if ($row) {
                        $row->delete();
                    }
                }
            }

            return $this->delete();
        }

        public function hydrate(array $data = [], $save = false)
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
                            } elseif (is_object($v)) {
                                $this->_data[$k] = $v->id;
                                $this->_data[str_replace('_id', '', $k)] = $v->toArray();
                            }
                        }

                        $this->_data[$k] = $v;
                    }
                }
            }

            return !$save ? $this : $this->save();
        }

        public function id()
        {
            return isAke($this->_data, 'id', null);
        }

        public function exists()
        {
            return null !== isAke($this->_data, 'id', null);
        }

        public function duplicate()
        {
            $this->_data['copyrow'] = sha1(__file__ . time());
            unset($this->_data['id']);
            unset($this->_data['created_at']);
            unset($this->_data['updated_at']);
            unset($this->_data['deleted_at']);

            return $this->save();
        }

        public function assoc()
        {
            return $this->_data;
        }

        public function toArray()
        {
            return $this->_data;
        }

        public function toJson()
        {
            return json_encode($this->_data);
        }

        public function __tostring()
        {
            return json_encode($this->_data);
        }

        public function __invoke($json = false)
        {
            return $json ? $this->save()->toJson() : $this->save();
        }

        public function deleteSoft()
        {
            $this->_data['deleted_at'] = time();

            return $this->save();
        }

        public function db()
        {
            return $this->_db;
        }

        public function attach($model, $attributes = [])
        {
            if (is_array($model)) {
                foreach ($model as $mod) {
                    lib('pivot')->attach($this, $mod, $attributes);
                }
            } else {
                if (!isset($this->_data['id'])) {
                    throw new \Exception('The model ' . $this->db()->table . ' is incorrect because it has no id and it cannot be attached.');
                }

                lib('pivot')->attach($this, $model, $attributes);
            }

            return $this;

            $m = !is_array($model) ? $model : Arrays::first($model);

            if (!isset($this->_data['id']) || !strlen($m->id)) {
                throw new Exception("Attach method requires a valid model.");
            }

            $mTable = $m->db()->table;

            $names = [$this->_db->table, $mTable];
            asort($names);
            $pivot = Inflector::lower('pivot' . implode('', $names));

            $db = Db::instance($this->_db->db, $pivot);

            if (is_array($model)) {
                foreach ($model as $mod) {
                    $id = (int) $mod->id;

                    $row = $mod->db()->find($id);

                    if ($row) {
                        $fieldAttach    = $mTable . '_id';
                        $fieldModel     = $this->_db->table . '_id';

                        $attach = $db->firstOrCreate([
                            $fieldAttach    => $id,
                            $fieldModel     => $this->_data['id']
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
                    $fieldModel     = $this->_db->table . '_id';

                    $attach = $db->firstOrCreate([
                        $fieldAttach    => $id,
                        $fieldModel     => $this->_data['id']
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
            if (is_array($model)) {
                foreach ($model as $mod) {
                    lib('pivot')->detach($this, $mod);
                }
            } else {
                lib('pivot')->detach($this, $model);
            }

            return $this;

            if (!isset($this->_data['id'])) {
                throw new Exception("detach method requires a valid model.");
            }

            $m = !is_array($model) ? $model : Arrays::first($model);

            if ($m instanceof Db) {
                $m = $m->model();
            }

            $all = false;

            if (!strlen($m->id)) {
                $all = true;
            }

            $mTable = $m->db()->table;

            $names = [$this->_db->table, $mTable];
            asort($names);
            $pivot = Inflector::lower('pivot' . implode('', $names));

            $db = Db::instance($this->_db->db, $pivot);

            if (is_array($model)) {
                foreach ($model as $mod) {
                    $id = (int) $mod->id;

                    $row = $mod->db()->find($id);

                    if ($row) {
                        $fieldAttach    = $mTable . '_id';
                        $fieldModel     = $this->_db->table . '_id';

                        $attach = $db->where([$fieldAttach , '=', (int) $id])
                        ->where([$fieldModel, '=', (int) $this->_data['id']])
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
                        $fieldModel     = $this->_db->table . '_id';

                        $attach = $db->where([$fieldAttach , '=', (int) $id])
                        ->where([$fieldModel, '=', (int) $this->_data['id']])
                        ->first(true);

                        if ($attach) {
                            $attach->delete();
                        }
                    }
                } else {
                    $fieldModel = $this->_db->table . '_id';

                    $attachs = $db->where([$fieldModel, '=', (int) $this->_data['id']])
                    ->exec(true);

                    if (!empty($attachs)) {
                        foreach ($attachs as $attach) {
                            $attach->delete();
                        }
                    }
                }
            }

            return $this;
        }

        public function pivot($model)
        {
            if ($model instanceof Db) {
                $model = $model->model();
            }

            $mTable = $model->db()->table;

            $names = [$this->_db->table, $mTable];
            asort($names);
            $pivot = Inflector::lower('pivot' . implode('', $names));

            return Db::instance($this->_db->db, $pivot);
        }

        public function pivots($model)
        {
            return lib('pivot')->retrieve($this, $model);

            if (!isset($this->_data['id'])) {
                throw new Exception("pivots method requires a valid model.");
            }

            $fieldModel = $this->_db->table . '_id';

            return $this->pivot($model)->where([$fieldModel, '=', (int) $this->_data['id']]);
        }

        public function hasPivot($model)
        {
            return lib('pivot')->has($this, $model);

            if (!isset($this->_data['id'])) {
                throw new Exception("hasPivot method requires a valid model.");
            }

            if ($model instanceof Db) {
                $model = $model->model();
            }

            $fieldModel = $this->_db->table . '_id';

            $count = $this->pivot($model)->where([$fieldModel, '=', (int) $this->_data['id']])->count();

            return $count > 0 ? true : false;
        }

        public function log()
        {
            $ns = isset($this->_data['id']) ? 'row_' . $this->_data['id'] : null;

            return $this->_db->log($ns);
        }

        public function actual()
        {
            return $this;
        }

        public function initial($model = false)
        {
            return $model ? new self($this->_initial) : $this->_initial;
        }

        public function cancel()
        {
            $this->_data = $this->_initial;

            return $this;
        }

        public function observer()
        {
            return new Observer($this);
        }

        public function getPivots($pivot)
        {
            return $this->getPivot($pivot, false);
        }

        public function getPivot($pivot, $first = true)
        {
            $res = call_user_func_array([lib('pivot'), 'retrieve'], [$this, $pivot]);

            return $first ? $res->first() : array_values($res->toArray());
        }

        public function oneToOne($pivot)
        {
            return $this->getPivot($pivot);
        }

        public function belongsToOne($pivot)
        {
            return $this->getPivot($pivot);
        }

        public function belongsToMany($pivot)
        {
            return $this->getPivot($pivot, false);
        }

        public function manyToMany($pivot)
        {
            return $this->getPivot($pivot, false);
        }

        public function oneToMany($pivot)
        {
            return $this->getPivot($pivot, false);
        }

        public function take($fk)
        {
            if (!isset($this->_data['id'])) {
                throw new Exception('id must be defined to use take.');
            }

            $db = fnmatch('*s', $fk) ? Db::instance($this->_db->db, substr($fk, 0, -1)) : Db::instance($this->_db->db, $fk);

            return $db->where([$this->_db->table . '_id', '=', (int) $this->_data['id']]);
        }

        public function incr($key, $by = 1)
        {
            $oldVal = isset($this->$key) ? $this->$key : 0;
            $newVal = $oldVal + $by;

            $this->$key = $newVal;

            return $this;
        }

        public function decr($key, $by = 1)
        {
            $oldVal = isset($this->$key) ? $this->$key : 1;
            $newVal = $oldVal - $by;

            $this->$key = $newVal;

            return $this;
        }

        public function through($t1, $t2)
        {
            $database = $this->_db->db;

            $db1 = Db::instance($database, $t1);

            $fk = $this->_db->table . '_id';

            $sub = $db1->where([$fk, '=', (int) $this->_data['id']])->cursor();

            $ids = [];

            foreach ($sub as $row) {
                $ids[] = $row['id'];
            }

            $fk2 = $t1 . '_id';

            return Db::instance($database, $t2)->where([$fk2, 'IN', implode(',', $ids)])->cursor()->toArray();
        }

        public function hasThrough($t1, $t2)
        {
            $database = $this->_db->db;

            $db1 = Db::instance($database, $t1);

            $fk = $this->_db->table . '_id';

            $sub = $db1->where([$fk, '=', (int) $this->_data['id']])->cursor();

            $ids = [];

            foreach ($sub as $row) {
                $ids[] = $row['id'];
            }

            $fk2 = $t1 . '_id';

            return Db::instance($database, $t2)->where([$fk2, 'IN', implode(',', $ids)])->cursor()->count() > 0 ? true : false;
        }

        public function countThrough($t1, $t2)
        {
            $database = $this->_db->db;

            $db1 = Db::instance($database, $t1);

            $fk = $this->_db->table . '_id';

            $sub = $db1->where([$fk, '=', (int) $this->_data['id']])->cursor();

            $ids = [];

            foreach ($sub as $row) {
                $ids[] = $row['id'];
            }

            $fk2 = $t1 . '_id';

            return Db::instance($database, $t2)->where([$fk2, 'IN', implode(',', $ids)])->cursor()->count();
        }

        public function timestamps()
        {
            return [
                'created_at' => lib('time')->createFromTimestamp(isAke($this->_data, 'created_at', time())),
                'updated_at' => lib('time')->createFromTimestamp(isAke($this->_data, 'updated_at', time()))
            ];
        }

        public function updated()
        {
            return lib('time')->createFromTimestamp(isAke($this->_data, 'updated_at', time()));
        }

        public function created()
        {
            return lib('time')->createFromTimestamp(isAke($this->_data, 'updated_at', time()));
        }

        public function fill(array $data)
        {
            return $this->hydrate($data);
        }

        public function fillAndSave(array $data)
        {
            return $this->hydrate($data)->save();
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
            $this->_data['updated_at'] = time();

            return $this->save();
        }

        public function associate($model)
        {
            $db = $model->db();
            $field = $db->table . '_id';

            $this->_data[$field] = $model->id;
            $this->_data[$db->table] = $model->toArray();

            return $this;
        }

        public function dissociate($model)
        {
            $db = $model->db();
            $field = $db->table . '_id';

            unset($this->_data[$field]);
            unset($this->_data[$db->table]);

            return $this;
        }
    }
