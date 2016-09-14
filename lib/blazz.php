<?php
    namespace Octo;

    class Blazz
    {
        private $query = [], $toWrite = [], $toDelete = [], $db, $table, $res, $file, $dir, $begin = false, $write = false;

        public function __construct($db = null, $table = null)
        {
            $this->db       = is_null($db) ? SITE_NAME : $db;
            $this->table    = is_null($table) ? 'core' : $table;

            $dir = Config::get('db.store', session_save_path());

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $dir .= DS . Inflector::urlize(Inflector::uncamelize($this->db));

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $this->dir = $dir . DS . Inflector::urlize(Inflector::uncamelize($this->table));

            if (!is_dir($this->dir)) {
                File::mkdir($this->dir);
            }

            if (!is_file($this->dir . DS . 'age.blazz')) {
                File::put($this->dir . DS . 'age.blazz', '');
            }

            $this->cursor = new Cursor($this);
        }

        public function instanciate($db = null, $table = null)
        {
            return new self($db, $table);
        }

        public function age($t = false)
        {
            $ts = filemtime($this->dir . DS . 'age.blazz');

            return !$t ? $ts : lib('time')->createFromTimestamp($ts);
        }

        public function __destruct()
        {
            $this->refresh();
        }

        public function refresh()
        {
            if (!$this->begin) {
                if (true === $this->write) {
                    if (!empty($this->toDelete)) {
                        foreach ($this->toDelete as $row) {
                            $id     = isAke($row, 'id');
                            $file   = $this->dir . DS . 'id' . DS . $id . '.blazz';

                            if (!is_dir($this->dir . DS . 'id')) {
                                File::mkdir($this->dir . DS . 'id');
                            }

                            File::delete($file);

                            foreach ($row as $k => $v) {
                                $file = $this->dir . DS . $k . DS . $id . '.blazz';

                                if (!is_dir($this->dir . DS . $k)) {
                                    File::mkdir($this->dir . DS . $k);
                                }

                                File::delete($file);
                            }

                            $file = $this->dir . DS . $id . '.blazz';
                            File::delete($file);
                            touch($this->dir . DS . 'age.blazz', time());
                        }
                    }

                    if (!empty($this->toWrite)) {
                        foreach ($this->toWrite as $row) {
                            $id = isAke($row, 'id');

                            $id = (int) $id;

                            $file = $this->dir . DS . 'id' . DS . $id . '.blazz';

                            if (!is_dir($this->dir . DS . 'id')) {
                                File::mkdir($this->dir . DS . 'id');
                            }

                            File::delete($file);
                            File::put($file, serialize($id));

                            foreach ($row as $k => $v) {
                                if ($k == 'id') {
                                    continue;
                                }

                                if (fnmatch ('*_id', $k)) {
                                    $v = (int) $v;
                                }

                                if (!is_dir($this->dir . DS . $k)) {
                                    File::mkdir($this->dir . DS . $k);
                                }

                                $file = $this->dir . DS . $k . DS . $id . '.blazz';
                                File::delete($file);
                                File::put($file, serialize($v));

                                $row[$k] = $v;
                            }

                            $file = $this->dir . DS . $id . '.blazz';
                            File::delete($file);
                            File::put($file, serialize($row));
                            touch($this->dir . DS . 'age.blazz', time());
                        }
                    }

                    $this->cursor = (new Cursor($this))->reset();

                    $this->write = false;
                }
            } else {
                throw new Exception("Please commit or rollback your transaction.");
            }

            return $this;
        }

        private function add($row)
        {
            $id = isAke($row, 'id', null);

            if ($id) {
                $this->write = true;

                $this->toWrite[] = $row;
            }

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
            $this->write = true;

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
            $this->write = true;

            $this->add($data);

            return $model ? $this->model($data) : $data;
        }

        private function update(array $data, $model = true)
        {
            $this->write = true;

            $data['updated_at'] = time();

            $old = $this->cursor->getRow($data['id'], false);

            if (is_null($old)) {
                $old = [];
            }

            $data = array_merge($old, $data);

            $this->delete($data['id']);

            $this->add($data);

            return $model ? $this->model($data) : $data;
        }

        public function delete($id)
        {
            $row = $this->cursor->getRow($id);

            $exists = !is_null($row);

            if ($exists) {
                $this->write        = true;
                $this->toDelete[]   = $row;
            }

            return $exists;
        }

        public function flush()
        {
            File::rmdir($this->dir);

            return $this;
        }

        public function find($id, $model = true)
        {
            $row = $this->cursor->getRow($id);

            if ($row) {
                return $model ? $this->model($row) : $row;
            }

            return null;
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

            return is_object($res) && $res instanceof Cursor ? $this->cursor : $res;
        }

        private function makeId()
        {
            $file = $this->dir . DS . 'lastid.blazz';

            if (is_file($file)) {
                $last = File::read($file);
                $new = $last + 1;

                File::delete($file);
                File::put($file, $new);

                return $new;
            }

            File::put($file, 1);

            return 1;
        }

        public function model(array $data = [])
        {
            return loadModel($this, $data);
        }

        public function db()
        {
            return $this->db;
        }

        public function table()
        {
            return $this->table;
        }

        public function dir()
        {
            return $this->dir;
        }

        public function cursor()
        {
            return $this->cursor;
        }

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

        public function fail()
        {
            return $this->rollback();
        }

        public function success()
        {
            return $this->commit();
        }

        public function transactional(callable $cb)
        {
            return call_user_func_array($cb, [$this]);
        }
    }
