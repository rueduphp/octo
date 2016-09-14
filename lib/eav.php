<?php
    namespace Octo;

    use PDO;

    class Eav
    {
        public static $connexions = [];
        public $connexion, $adapter, $db, $table, $db_id, $table_id, $age;

        public function __construct($db = null, $table = null, $adapter = 'mysql')
        {
            $db     = is_null($db)      ? def('SITE_NAME', 'site')  : $db;
            $table  = is_null($table)   ? 'core'                    : $table;

            $this->db       = $db;
            $this->table    = $table;
            $this->adapter  = $adapter;

            $this->connect();
            $this->init();

            $this->cursor = lib('cursoreav', [$this]);
        }

        private function init()
        {
            $key = $this->db . '.db_id';

            $db_id = $this->getOrCache($key, function () {
                $q = "INSERT INTO odatabase (name) VALUES (" . $this->quote($this->db) . ");";
                $this->q($q);

                return $this->connexion->lastInsertId();
            });

            $key = $this->table . '.table_id';

            $table_id = $this->getOrCache($key, function () {
                $q = "INSERT INTO otable (name) VALUES (" . $this->quote($this->table) . ");";
                $this->q($q);

                return $this->connexion->lastInsertId();
            });

            $this->age      = $this->age();
            $this->db_id    = $db_id;
            $this->table_id = $table_id;
        }

        public function fieldId($field)
        {
            $key = $field . '.field.id';

            return $this->getOrCache($key, function () use ($field) {
                $q = "INSERT INTO ofield (name) VALUES (" . $this->quote($field) . ");";
                $this->q($q);

                return $this->connexion->lastInsertId();
            });
        }

        public function fieldName($id)
        {
            $key = $id . '.field.name';

            return $this->getOrCache($key, function () use ($id) {
                $q = "SELECT name FROM ofield WHERE id = $id;";
                $res = $this->q($q);

                if (is_array($res)) {
                    $count = count($res);
                } else {
                    $count = $res->rowCount();
                }

                if ($count < 1 && 'sqlite' != $this->adapter) {
                    throw new Exception("Field $id does not exist.");
                }

                $exists = false;

                foreach ($res as $row) {
                    $exists = true;

                    return $row['name'];
                }

                if (!$exists) {
                    throw new Exception("Field $id does not exist.");
                }
            });
        }

        private function makeId()
        {
            $key = $this->db . '.' . $this->table . '.ids';

            return $this->incrCache($key);
        }

        private function connect()
        {
            $key    = sha1($this->db . $this->table . $this->adapter);
            $i      = isAke(self::$connexions, $key, null);

            if (!$i) {
                $adapter    = $this->adapter;

                if ('mysql' == $adapter) {
                    $db         = Config::get($adapter . '.db', 'Octo');
                    $host       = Config::get($adapter . '.host', 'localhost');
                    $username   = Config::get($adapter . '.username', 'root');
                    $password   = Config::get($adapter . '.password', 'root');
                    $dsn        = "$adapter:dbname=$db;host=$host";

                    $i = newInstance('\\PDO', [$dsn, $username, $password]);
                } elseif ('sqlite' == $adapter) {
                    $file   = Config::get('eav.lite', path('storage') . '/db/okv.db');
                    $i      = newInstance('\\PDO', ['sqlite:' . $file]);
                }

                self::$connexions[$key] = $i;
            }

            $this->connexion = $i;

            return $i;
        }

        public function quote($value, $parameterType = PDO::PARAM_STR)
        {
            if (null === $value) {
                return "NULL";
            }

            if (is_string($value)) {
                return $this->connexion->quote($value, $parameterType);
            }

            return $value;
        }

        public function q($query)
        {
            $res = $this->connexion->prepare($query);

            if (is_object($res)) $res->execute();

            return $res;
        }

        public function cleanCache()
        {
            $q = "DELETE FROM okv WHERE oexpire > 0 AND oexpire < " . time();
            $this->q($q);
        }

        public function untilCache($k, callable $c, $maxAge = null, $args = [])
        {
            $keyAge = $k . '.maxage';
            $v      = $this->getCache($k);

            if ($v) {
                if (is_null($maxAge)) {
                    return $v;
                }

                $age = $this->getCache($keyAge);

                if (!$age) {
                    $age = $maxAge - 1;
                }

                if ($age >= $maxAge) {
                    return $v;
                } else {
                    $this->deleteCache($k);
                    $this->deleteCache($keyAge);
                }
            }

            $data = call_user_func_array($c, $args);

            $this->setCache($k, $data);

            if (!is_null($maxAge)) {
                if ($maxAge < 1000000000) {
                    $maxAge = ($maxAge * 60) + time();
                }

                $this->setCache($keyAge, $maxAge);
            }

            return $data;
        }

        public function keysCache($pattern)
        {
            $this->cleanCache();
            $pattern    = str_replace('*', '%', $pattern);
            $q          = "SELECT okey FROM okv WHERE okey LIKE '$pattern'";
            $res        = $this->q($q);

            if (is_array($res)) {
                $count = count($res);
            } else {
                $count = $res->rowCount();
            }

            if ($count < 1 && 'sqlite' != $this->adapter) {
                return [];
            }

            $collection = [];

            foreach ($res as $row) {
                array_push($collection, $row['okey']);
            }

            return $collection;
        }

        public function getCache($key, $default = null)
        {
            $this->cleanCache();
            $q = "SELECT ovalue AS value FROM okv WHERE okey = " . $this->quote($key);
            $res = $this->q($q);

            if (is_array($res)) {
                $count = count($res);
            } else {
                $count = $res->rowCount();
            }

            if ($count < 1 && 'sqlite' != $this->adapter) {
                return $default;
            }

            foreach ($res as $row) {
                return unserialize($row['value']);
            }

            return $default;
        }

        public function hasCache($k)
        {
            return 'octodummy' != $this->getCache($k, 'octodummy');
        }

        public function getOrCache($k, callable $v)
        {
            $value = $this->getCache($k);

            if (!$value) {
                $value = $v();
                $this->setCache($k, $value);
            }

            return $value;
        }

        public function setCache($key, $value, $expire = 0)
        {
            if ('mysql' == $this->adapter) {
                $q = "INSERT INTO okv (okey, ovalue, oexpire)
                VALUES (
                    " . $this->quote($key) . ",
                    " . $this->quote(serialize($value)) . ",
                    " . $this->quote($expire) . "
                )
                ON DUPLICATE KEY
                UPDATE ovalue = " . $this->quote(serialize($value)) . ", oexpire = " . $this->quote($expire) . ";";
            } elseif ('sqlite' == $this->adapter) {
                $this->delCache($key);

                $q = "INSERT INTO okv (okey, ovalue, oexpire)
                VALUES (
                    " . $this->quote($key) . ",
                    " . $this->quote(serialize($value)) . ",
                    " . $this->quote($expire) . "
                );";
            }

            $res = $this->q($q);

            return $this;
        }

        public function expireCache($key, $ttl = 3600)
        {
            $val = $this->getCache($key);

            if (!empty($val)) {
                return $this->setCache($key, $val, time() + $ttl);
            }

            return false;
        }

        public function deleteCache($key)
        {
            return $this->delCache($key);
        }

        public function delCache($key)
        {
            $q = "DELETE FROM okv WHERE okey = " . $this->quote($key);
            $res = $this->q($q);

            return $this;
        }

        public function incrCache($key, $by = 1)
        {
            $old = $this->getCache($key, 0);
            $new = $old + $by;

            $this->setCache($key, $new);

            return $new;
        }

        public function decrCache($key, $by = 1)
        {
            $old = $this->getCache($key, 0);
            $new = $old - $by;

            $this->setCache($key, $new);

            return $new;
        }

        public function instanciate($db = null, $table = null)
        {
            return new self($db, $table);
        }

        public function age($t = null)
        {
            $key = $this->db . '.' . $this->table . '.age';

            if (empty($t)) {
                return $this->getOrCache($key, function () {
                    return time();
                });
            } else {
                $this->setCache($key, $t);
            }
        }

        private function add($row, $delete = false)
        {
            $id = isAke($row, 'id', null);

            if ($delete) {
                $this->delete($id);
            }

            foreach ($row as $k => $v) {
                if ($k == 'id') continue;

                $q = "INSERT INTO odata (odatabase_id, otable_id, ofield_id, oid, ovalue)
                VALUES (
                    " . $this->quote($this->db_id) . ",
                    " . $this->quote($this->table_id) . ",
                    " . $this->quote($this->fieldId(strtolower($k))) . ",
                    " . $this->quote($id) . ",
                    " . $this->quote(serialize($v)) . "
                )";

                $this->q($q);
            }

            $key = $this->db . '.' . $this->table . '.' . $id . '.row';
            $this->setCache($key, $row);

            $key = $this->db . '.' . $this->table . '.' . $id . '.age';
            $this->setCache($key, time());

            $this->age(time());

            return $this;
        }

        public function push($row)
        {
            return $this->add($row);
        }

        public function create(array $data = [])
        {
            return $this->model($data);
        }

        public function save(array $data, $model = true)
        {
            $id = isAke($data, 'id', null);

            if ($id && is_int($id)) {
                return $this->update($data, $model);
            }

            $data['id']         = $this->makeId();
            $data['created_at'] = $data['updated_at'] = time();

            return $this->insert($data, $model);
        }

        private function insert(array $data, $model = true)
        {
            $this->add($data);

            return $model ? $this->model($data) : $data;
        }

        private function update(array $data, $model = true)
        {
            $data['updated_at'] = time();

            $old = $this->getRow($data['id'], false);

            if (is_null($old)) {
                $old = [];
            }

            $data = array_merge($old, $data);

            $this->delete($data['id']);

            $this->add($data, true);

            return $model ? $this->model($data) : $data;
        }

        public function delete($id)
        {
            $row = $this->getRow($id);

            $exists = !is_null($row);

            if ($exists) {
                $q = "DELETE FROM odata WHERE oid = $id AND odatabase_id = " . $this->db_id . " AND otable_id = " . $this->table_id;
                $this->q($q);
                $key = $this->db . '.' . $this->table . '.' . $id . '.row';
                $this->delCache($key);
                $key = $this->db . '.' . $this->table . '.' . $id . '.age';
                $this->delCache($key);
                $this->age(time());
            }

            return $exists;
        }

        public function drop()
        {
            $lines = 0;

            $q = "SELECT oid FROM odata WHERE odatabase_id = " . $this->db_id . " AND otable_id = " . $this->table_id;

            $res = $this->q($q);

            if (is_array($res)) {
                $count = count($res);
            } else {
                $count = $res->rowCount();
            }

            if ($count < 1 && 'sqlite' != $this->adapter) {
                return $lines;
            }

            foreach ($res as $row) {
                $id = $row['oid'];
                $this->delete($id);
                $lines++;
            }

            return $lines;
        }

        public function find($id, $model = true)
        {
            $row = $this->getRow((int) $id);

            if ($row) {
                return $model ? $this->model($row) : $row;
            }

            return null;
        }

        public function getRow($id, $eager = true)
        {
            $key = $this->db . '.' . $this->table . '.' . $id . '.row';

            return $this->getOrCache($key, function () use ($id) {
                $q = "SELECT ofield_id, ovalue FROM odata WHERE oid = $id AND odatabase_id = " . $this->db_id . " AND otable_id = " . $this->table_id;
                $data = ['id' => $id];

                $res = $this->q($q);

                if (is_array($res)) {
                    $count = count($res);
                } else {
                    $count = $res->rowCount();
                }

                if ($count < 1 && 'sqlite' != $this->adapter) {
                    return null;
                }

                $exists = false;

                foreach ($res as $row) {
                    $v = unserialize($row['ovalue']);
                    $f = $this->fieldName((int) $row['ofield_id']);

                    if (in_array($f, ['created_at', 'updated_at'])) {
                        $v = (int) $v;
                    }

                    if (fnmatch('*_id', $f) && true === $eager) {
                        $fkTable                    = str_replace('_id', '', $f);
                        $fkId = $v                  = (int) $v;
                        $data['fk_' . $fkTable]     = $this->instanciate($this->db, $fkTable)->find((int) $fkId, false);
                    }

                    $data[$f] = $v;
                    $exists = true;
                }

                return !$exists ? null : $data;
            });
        }

        public function model($row)
        {
            $model = o($row);

            $model->fn('save', function () use ($model) {
                return $this->save($model->toArray());
            });

            $model->fn('delete', function () use ($row) {
                if (isset($row['id'])) {
                    return $this->delete($row['id']);
                } else {
                    return false;
                }
            });

            $model->fn('table', function () {
                return $this->table;
            });

            $model->fn('db', function () {
                return $this->db;
            });

            $model->fn('adapter', function () {
                return $this->adapter;
            });

            $file = path('app') . '/models/eav/' . $this->db . '/' . $this->table . '.php';

            if (file_exists($file)) {
                $cbs = include $file;

                foreach ($cbs as $cbname => $cb) {
                    if (is_callable($cb)) {
                        $model->fn($cbname, $cb);
                    }
                }
            }

            return $model;
        }

        public function findOrFail($id, $model = true)
        {
            $row = $this->find($id, false);

            if (!$row) {
                throw new Exception("The row $id does not exist.");
            } else {
                return $model ? $this->model($row) : $row;
            }
        }

        public function firstOrCreate($conditions)
        {
            $data = $this->cursor->select(array_keys($conditions));

            $row = Arrays::firstOne($data, function ($k, $row) use ($conditions) {
                foreach ($conditions as $k => $v) {
                    if (fnmatch('*_id', $k) || $k == 'id') {
                        $v = (int) $v;
                    }

                    if ($row[$k] !== $v) {
                        return false;
                    }
                }

                return true;
            }, null);

            if (!$row) {
                return $this->save($conditions, true);
            } else {
                return $this->model($row);
            }
        }

        public function firstOrNew($conditions)
        {
            $data = $this->cursor->select(array_keys($conditions));

            $row = Arrays::firstOne($data, function ($k, $row) use ($conditions) {
                foreach ($conditions as $k => $v) {
                    if ($row[$k] != $v) {
                        return false;
                    }
                }

                return true;
            }, null);

            if (!$row) {
                return $this->model($conditions);
            } else {
                return $this->model($row);
            }
        }

        public function __call($m, $a)
        {
            if (fnmatch('findBy*', $m) && strlen($m) > 'findBy') {
                $field = Inflector::uncamelize(Inflector::lower(str_replace('findBy', '', $m)));

                if (strlen($field) > 0 && !empty($a)) {
                    return $this->where([$field, '=', current($a)]);
                }
            }

            if (fnmatch('countBy*', $m) && strlen($m) > 'countBy') {
                $field = Inflector::uncamelize(Inflector::lower(str_replace('countBy', '', $m)));

                if (strlen($field) > 0 && !empty($a)) {
                    return $this->where([$field, '=', current($a)])->count();
                }
            }

            if (fnmatch('groupBy*', $m) && strlen($m) > 'groupBy') {
                $field = Inflector::uncamelize(Inflector::lower(str_replace('groupBy', '', $m)));

                if (strlen($field) > 0) {
                    return $this->groupBy($field);
                }
            }

            if (fnmatch('findOneBy*', $m) && strlen($m) > 'findOneBy') {
                $field = Inflector::uncamelize(Inflector::lower(str_replace('findOneBy', '', $m)));

                if (strlen($field) > 0 && !empty($a)) {
                    $model = false;

                    if (count($a) == 2) {
                        if (true === end($a)) {
                            $model = true;
                        }
                    }

                    return $this->where([$field, '=', current($a)])->first($model);
                }
            }

            if (fnmatch('firstBy*', $m) && strlen($m) > 'firstBy') {
                $field = Inflector::uncamelize(Inflector::lower(str_replace('firstBy', '', $m)));

                if (strlen($field) > 0 && !empty($a)) {
                    $model = false;

                    if (count($a) == 2) {
                        if (true === end($a)) {
                            $model = true;
                        }
                    }

                    return $this->where([$field, '=', current($a)])->first($model);
                }
            }

            if (fnmatch('lastBy*', $m) && strlen($m) > 'lastBy') {
                $field = Inflector::uncamelize(Inflector::lower(str_replace('lastBy', '', $m)));

                if (strlen($field) > 0 && !empty($a)) {
                    $model = false;

                    if (count($a) == 2) {
                        if (true === end($a)) {
                            $model = true;
                        }
                    }

                    return $this->where([$field, '=', current($a)])->last($model);
                }
            }

            $this->query[] = func_get_args();

            $res = call_user_func_array([$this->cursor, $m], $a);

            return is_object($res) && $res instanceof Cursoreav ? $this->cursor : $res;
        }

        public function db()
        {
            return $this->db;
        }

        public function table()
        {
            return $this->table;
        }

        public function cursor()
        {
            return $this->cursor;
        }

        /* Transactions */

        public function begin()
        {
            $this->begin = true;

            return $this;
        }

        public function rollback()
        {
            $this->toWrite  = [];
            $this->toDelete = [];
            $this->write    = false;
            $this->begin    = false;

            return $this;
        }

        public function commit()
        {
            $this->begin = false;

            return $this->refresh();
        }

        /* Alias Rollback */
        public function fail()
        {
            return $this->rollback();
        }

        /* Alias commit */
        public function success()
        {
            return $this->commit();
        }

        public function transactional(callable $cb)
        {
            return call_user_func_array($cb, [$this]);
        }
    }
