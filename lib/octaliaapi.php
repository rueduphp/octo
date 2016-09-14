<?php
    namespace Octo;

    class OctaliaApi
    {
        public $database, $table, $token, $url, $username, $password, $response, $wheres = [], $params = [], $hooks = [
            'before' => [],
            'after' => [],
        ];

        public function __construct($url, $username, $password)
        {
            $this->url      = $url;
            $this->username = $username;
            $this->password = $password;
        }

        public function hook($when, callable $cb)
        {
            $this->checkDefined();

            Arrays::set($this->hooks, $when, $cb);

            return $this;
        }

        public function database($database)
        {
            $this->database = $database;

            return $this;
        }

        public function db($database)
        {
            $this->database = $database;

            return $this;
        }

        public function table($table)
        {
            $this->table = $table;

            return $this;
        }

        public function from($table)
        {
            $this->table = $table;

            return $this;
        }

        public function age()
        {
            return $this->checkDefined()->call('age')->checkError()->getData('age', time());
        }

        public function lastid()
        {
            return $this->checkDefined()->call('lastid')->checkError()->getData('lastid', 1);
        }

        public function count()
        {
            $key = 'count.' . $this->database . '.' . $this->table;
            $key .= serialize($this->wheres);

            $key = sha1($key);

            return fmr('oapi')->until($key, function () {
                return $this->checkDefined()->call('count', ['query' => $this->wheres])->checkError()->getData('count', 0);
            }, $this->age());
        }

        public function find($id, $model = true)
        {
            $key = 'find.' . $this->database . '.' . $this->table . '.' . $id;

            $row = fmr('oapi')->until($key, function () use ($id) {
                return $this->checkDefined()->call('find', ['id' => $id])->checkError()->getData('row', null);
            }, $this->age());

            if ($row) {
                if ($model) {
                    $row = $this->model($row);
                }
            }

            return $raw;
        }

        public function where($conditions)
        {
            $this->checkDefined();

            if (!is_array($conditions)) {
                $this->fail(['message' => 'The argument of where method must be an array.']);
            }

            if (count($conditions) < 3) {
                $this->fail(['message' => 'The argument of where method must contain at least 3 values like ["id", "=", 15].']);
            }

            if (count($conditions) == 3) {
                $conditions[] = 'AND';
            }

            $this->wheres[] = $conditions;

            return $this;
        }

        public function get()
        {
            $key = 'get.' . $this->database . '.' . $this->table;
            $key .= serialize($this->wheres);

            $key = sha1($key);

            return fmr('oapi')->until($key, function () {
                return $this->checkDefined()->call('get', ['query' => $this->wheres])->checkError()->getData('rows', []);
            }, $this->age());
        }

        public function sortBy($field)
        {
            $results = coll($this->get())->sortBy($field);

            return array_values($results->toArray());
        }

        public function sortByDesc($field)
        {
            $results = coll($this->get())->sortByDesc($field);

            return array_values($results->toArray());
        }

        public function groupBy($field)
        {
            $results = coll($this->get())->groupBy($field);

            return $results->toArray();
        }

        public function save(array $data)
        {
            $id = isAke($data, 'id', null);

            if ($id && is_int($id)) {
                return $this->update($id, $data);
            }

            return $this->insert($data);
        }

        public function insert(array $row)
        {
            return $this->checkDefined()->call('insert', ['row' => $row])->checkError()->getData('status', null);
        }

        public function update($id, array $row)
        {
            return $this->checkDefined()->call('update', ['id' => $id, 'row' => $row])->checkError()->getData('status', null);
        }

        public function delete($id)
        {
            return $this->checkDefined()->call('delete', ['id' => $id, 'row' => $row])->checkError()->getData('status', null);
        }

        public function sum($field)
        {
            return $this->agregate('sum', $field);
        }

        public function avg($field)
        {
            return $this->agregate('avg', $field);
        }

        public function min($field)
        {
            return $this->agregate('min', $field);
        }

        public function max($field)
        {
            return $this->agregate('max', $field);
        }

        protected function agregate($type, $field)
        {
            $key = 'agregate.' . $type . '.' . $field;
            $key .= $this->database . '.' . $this->table;
            $key .= serialize($this->wheres);

            $key = sha1($key);

            return fmr('oapi')->until($key, function () use ($type, $field) {
                return $this->checkDefined()->call($type, ['query' => $this->wheres, 'field' => $field])->checkError()->getData($type, 0);
            }, $this->age());
        }

        public function exists()
        {
            if (!empty($this->wheres)) {
                return $this->count() > 0;
            }

            return false;
        }

        public function isEmpty()
        {
            return $this->count() == 0;
        }

        public function isNotEmpty()
        {
            return 0 < $this->count();
        }

        public function paginate($page, $perPage)
        {
            return array_slice((array) $this->get(), ($page - 1) * $perPage, $perPage);
        }

        public function getToken($action)
        {
            $this->params = [
                'action'    => 'token',
                'to'        => $action,
                'username'  => $this->username,
                'password'  => $this->password,
            ];

            if (isset($this->database) || isset($this->table)) {
                $this->params['database'] = $this->database;
                $this->params['table']    = $this->table;
            }

            return $this->curl()->checkError()->getData('token');
        }

        private function call($action, $params = array())
        {
            try {
                $token = $this->getToken($action);

                if (empty($token)) {
                    $this->fail(['message' => 'Invalid token']);
                }

                $params['action']   = $action;
                $params['token']    = $token;

                $hookBeforeAll  = Arrays::get($this->hooks, 'before.*', null);
                $hookBefore     = Arrays::get($this->hooks, 'before.' . $action, null);

                $hookAfterAll   = Arrays::get($this->hooks, 'after.*', null);
                $hookAfter      = Arrays::get($this->hooks, 'after.' . $action, null);

                $this->params   = $params;

                if (is_callable($hookBeforeAll)) {
                    $hookBeforeAll($this);
                }

                if (is_callable($hookBefore)) {
                    $hookBefore($this);
                }

                $this->curl();

                if (is_callable($hookAfterAll)) {
                    $hookAfterAll($this);
                }

                if (is_callable($hookAfter)) {
                    $hookAfter($this);
                }
            } catch (\Exception $e) {
                $this->fail(['message' => 'An error occured : ' . $e->getMessage()]);
            }

            return $this;
        }

        private function curl()
        {
            try {
                $ch = curl_init();

                $params = http_build_query($this->params);

                curl_setopt($ch, CURLOPT_URL, $this->url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

                $res1   = curl_exec($ch);
                $res    = json_decode($res1, true);

                $this->response = $res;
            } catch (\Exception $e) {
                $this->fail(['message' => 'An error occured']);
            }

            return $this;
        }

        protected function getData($k, $d = null)
        {
            if (is_array($this->response)) {
                return isAke($this->response, $k, $d);
            }

            return $d;
        }

        protected function fail(array $array = [])
        {
            throw new Exception(isAke($array, 'message', 'An error occured.'));
        }

        protected function checkError()
        {
            $error = $this->getData('error');

            if ($error) {
                $this->fail(['message' => $error]);
            }

            return $this;
        }

        protected function checkDefined()
        {
            if (!isset($this->database) || !isset($this->table)) {
                $this->fail(['message' => "You must define a database and a table before."]);
            }

            return $this;
        }

        public function model($row)
        {
            $this->checkDefined();

            $model = o($row);

            $model->model(Inflector::camelize($this->database . '_' . $this->table . '_model'))
            ->fn('save', function () use ($model) {
                return $this->save($model->toArray());
            })->fn('delete', function () use ($row) {
                if (isset($row['id'])) {
                    return $this->delete($row['id']);
                } else {
                    return false;
                }
            })->fn('copy', function ($create = true) use ($row) {
                unset($row['id']);
                unset($row['created_at']);
                unset($row['updated_at']);

                return $this->save($row);
            })->fn('table', function () {
                return $this->table;
            })->fn('db', function () {
                return $this->db;
            });

            return $model;
        }

        public function __call($m, $a)
        {
            if ('on' == $m) {
                return call_user_func_array([$this, 'db'], $a);
            }

            if ('or' == $m) {
                $c = current($a);
                $c[] = 'OR';
                $a = [$c];

                return call_user_func_array([$this, 'where'], $a);
            }

            if ('and' == $m) {
                $c = current($a);
                $c[] = 'AND';
                $a = [$c];

                return call_user_func_array([$this, 'where'], $a);
            }

            if ('xor' == $m) {
                $c = current($a);
                $c[] = 'XOR';
                $a = [$c];

                return call_user_func_array([$this, 'where'], $a);
            }
        }

        public function slice($offset, $length = null)
        {
            return array_values(array_slice((array) $this->get(), $offset, $length, true));
        }

        public function map(callable $callback)
        {
            $data       = $this->get();

            $results    = coll($data)->each($callback);

            return  array_values($results->toArray());
        }

        public function filter(callable $callback)
        {
            $data       = $this->get();
            $results    = coll($data)->filter($callback);

            return  array_values($results->toArray());
        }

        public function splice($offset, $length = null, $replacement = [])
        {
            if (func_num_args() == 1) {
                return array_values(array_splice((array) $this->get(), $offset));
            }

            return array_values(array_splice((array) $this->get(), $offset, $length, $replacement));
        }

        public function average($field)
        {
            return $this->avg($field);
        }

        public function take($limit = null)
        {
            if ($limit < 0) {
                return $this->slice($limit, abs($limit));
            }

            return $this->slice(0, $limit);
        }

        public function limit($o, $l)
        {
            return $this->slice($o, $l);
        }

        public function like($field, $value)
        {
            return $this->where($field, 'like', $value);
        }

        public function notLike($field, $value)
        {
            return $this->where($field, 'not like', $value);
        }

        public function findBy($field, $value)
        {
            return $this->where($field, '=', $value);
        }

        public function firstBy($field, $value, $model = false)
        {
            return $this->where($field, '=', $value)->first($model);
        }

        public function lastBy($field, $value, $model = false)
        {
            return $this->where($field, '=', $value)->last($model);
        }

        public function in($field, array $values)
        {
            return $this->where($field, 'in', $values);
        }

        public function notIn($field, array $values)
        {
            return $this->where($field, 'not in', $values);
        }

        public function rand($default = null)
        {
            $items = (array) $this->get();

            if (!empty($items)) {
                shuffle($items);

                $row = current($items);

                return $row;
            }

            return $default;
        }

        public function between($field, $min, $max)
        {
            return $this->where($field, 'between', [$min, $max]);
        }

        public function notBetween($field, $min, $max)
        {
            return $this->where($field, 'not between', [$min, $max]);
        }

        public function isNull($field)
        {
            return $this->where($field, 'is', 'null');
        }

        public function isNotNull($field)
        {
            return $this->where($field, 'is not', 'null');
        }

        public function post($create = false)
        {
            return $this->save($_POST);
        }

        public function lt($field, $value)
        {
            return $this->where([$field, '<', $value]);
        }

        public function gt($field, $value)
        {
            return $this->where([$field, '>', $value]);
        }

        public function lte($field, $value)
        {
            return $this->where([$field, '<=', $value]);
        }

        public function gte($field, $value)
        {
            return $this->where([$field, '>=', $value]);
        }

        public function before($date, $exact = true)
        {
            if (!is_int($date)) {
                $date = (int) $date->timestamp;
            }

            return $exact ? $this->lt('created_at', $date) : $this->lte('created_at', $date);
        }

        public function after($date, $exact = true)
        {
            if (!is_int($date)) {
                $date = (int) $date->timestamp;
            }

            return $exact ? $this->gt('created_at', $date) : $this->gte('created_at', $date);
        }

        public function when($field, $op, $date)
        {
            if (!is_int($date)) {
                $date = (int) $date->timestamp;
            }

            return $this->where([$field, $op, $date]);
        }

        public function first($model = false)
        {
            $i      = $this->get();
            $row    = current($i);

            if (!$row) return null;

            return $model ? $this->model($row) : $row;
        }

        public function last($model = false)
        {
            $i      = $this->get();
            $row    = end($i);

            if (!$row) return null;

            return $model ? $this->model($row) : $row;
        }

        public function firstOrFail($model = false)
        {
            $row = $this->first();

            if (!$row) {
                throw new Exception("Results set is empty.");
            } else {
                return $model ? $this->model($row) : $row;
            }
        }

        public function lastOrFail($model = false)
        {
            $row = $this->last();

            if (!$row) {
                throw new Exception("Results set is empty.");
            } else {
                return $model ? $this->model($row) : $row;
            }
        }

        public function collection()
        {
            return coll($this->get());
        }

        public function firstOrCreate($conditions)
        {
            $keyCache = sha1('search.' . $this->database . '.' . $this->table . '.' . serialize($conditions));

            $row = fmr('opai')->until($keyCache, function () use ($conditions) {
                $data = $this->get();

                return Arrays::firstOne($data, function ($k, $row) use ($conditions) {
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
            }, $this->age());

            if (!$row) {
                return $this->insert($conditions);
            } else {
                return $this->find($row['id']);
            }
        }

        public function firstOrNew($conditions)
        {
            $keyCache = sha1('search.' . $this->database . '.' . $this->table . '.' . serialize($conditions));

            $row = fmr('opai')->until($keyCache, function () use ($conditions) {
                $data = $this->get();

                return Arrays::firstOne($data, function ($k, $row) use ($conditions) {
                    foreach ($conditions as $k => $v) {
                        if ($row[$k] != $v) {
                            return false;
                        }
                    }

                    return true;
                }, null);
            }, $this->age());

            if (!$row) {
                return $this->model($conditions);
            } else {
                return $this->find($row['id']);
            }
        }

        public function getQuery()
        {
            return $this->query;
        }
    }
