<?php
    namespace Octo;

    class Cursoreav implements \Countable, \Iterator
    {
        private $resource, $db, $position, $closure, $query = [];

        public function __construct($db, callable $closure = null)
        {
            $this->db = $db;

            $this->dir = 'eav.' . $db->db . '.' . $db->table;
            $this->age = $db->age();

            $this->resource = Arrays::makeResource($this->ids());
            $this->position = 0;

            $this->closure = $closure;

            $this->count();
        }

        public function reset()
        {
            $this->position = 0;
            $this->resource = null;
            $this->closure  = null;
            $this->query    = [];

            $this->resource = Arrays::makeResource($this->ids());

            $this->count    = count($this->getIterator());

            return $this;
        }

        public function ids()
        {
            $keyCache = sha1('eav.ids.' . $this->dir);

            $ids =  $this->db->untilCache($keyCache, function () {
                $pattern    = $this->db->db . '.' . $this->db->table . '.*.row';
                $keys       = $this->db->keysCache($pattern);

                $ids = [];

                foreach ($keys as $row) {
                    $id = str_replace([$this->db->db . '.' . $this->db->table . '.', '.row'], '', $row);

                    $ids[] = (int) $id;
                }

                return $ids;
            }, $this->age);

            return $ids;
        }

        public function fields()
        {
            $keyCache = sha1('fields.' . $this->dir);

            return $this->db->untilCache($keyCache, function () {
                $fields = [];

                $q = "SELECT ofield_id FROM odata WHERE odatabase_id = " . $this->db->db_id . " AND otable_id = " . $this->db->table_id;

                $res = $this->db->q($q);

                if (is_array($res)) {
                    $count = count($res);
                } else {
                    $count = $res->rowCount();
                }

                if ($count < 1 && 'sqlite' != $this->db->adapter) {
                    return [];
                }

                foreach ($res as $row) {
                    $fields[] = $this->db->fieldName($row['ofield_id']);
                }

                return $fields;
            }, $this->age);
        }

        public function __destruct()
        {
            if (is_resource($this->resource)) {
                fclose($this->resource);
            }

            $this->reset();
        }

        public function getIterator()
        {
            $cursor = Arrays::makeFromResource($this->resource);
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            return \SplFixedArray::fromArray($cursor);
        }

        public function count($return = true)
        {
            if (!isset($this->count) || is_null($this->count)) {
                $this->count = count($this->getIterator());
            }

            return $return ? $this->count : $this;
        }

        public function select($fields = null)
        {
            $data = [];

            if (is_null($fields)) {
                $fields = $this->fields();
            }

            if (is_string($fields)) {
                $fields = [$fields];
            }

            if (!in_array('id', $fields)) {
                $fields[] = 'id';
            }

            foreach ($this->getIterator() as $id) {
                $data[$id] = [];

                foreach ($fields as $field) {
                    $data[$id][$field] = $this->getFieldValueById($field, $id);
                }
            }

            return $data;
        }

        public function getFieldValueById($field, $id, $d = null)
        {
            $row = $this->db->getRow($id);

            if ($row) {
                return isAke($row, $field, $d);
            }

            return $d;
        }

        public function getNext()
        {
            if (isset($this->resource[$this->position])) {
                $row = $this->db->getRow($this->resource[$this->position]);

                $this->position++;

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                return $row;
            }

            return false;
        }

        public function getPrev()
        {
            $this->position--;

            if (isset($this->resource[$this->position])) {
                $row = $this->db->getRow($this->resource[$this->position]);

                $this->position++;

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                return $row;
            }

            return false;
        }

        public function seek($pos = 0)
        {
            $this->position = $pos;

            return $this;
        }

        public function one($model = false)
        {
            return $this->seek()->current($model);
        }

        public function current($model = false)
        {
            $cursor = $this->getIterator();

            if (isset($cursor[$this->position])) {
                $row = $this->db->getRow($cursor[$this->position]);

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                return $model ? $this->db->model($row) : $row;
            }

            return false;
        }

        public function rewind()
        {
            $this->position = 0;
        }

        public function key()
        {
            return $this->position;
        }

        public function next()
        {
            ++$this->position;
        }

        public function valid()
        {
            $cursor = $this->getIterator();

            return isset($cursor[$this->position]);
        }

        public function each(callable $closure)
        {
            $row = $this->getNext();

            if ($row) {
                return $closure($row);
            }

            $this->reset();

            return false;
        }

        public function slice($offset, $length = null)
        {
            $this->resource = Arrays::makeResource(array_slice((array) $this->getIterator(), $offset, $length, true));

            $this->count = count($this->getIterator());

            return $this;
        }

        public function sum($field = 'id')
        {
            $this->query[] = ['sum' => $field];

            $keyCache = sha1('sum.' . $this->dir . $field . serialize($this->query));

            return $this->db->untilCache($keyCache, function () use ($field) {
                return coll($this->select($field))->sum($field);
            }, $this->age);
        }

        public function min($field = 'id')
        {
            $this->query[] = ['min' => $field];

            $keyCache = sha1('min.' . $this->dir . $field . serialize($this->query));

            return $this->db->untilCache($keyCache, function () use ($field) {
                return coll($this->select($field))->min($field);
            }, $this->age);
        }

        public function max($field = 'id')
        {
            $this->query[] = ['max' => $field];

            $keyCache = sha1('max.' . $this->dir . $field . serialize($this->query));

            return $this->db->untilCache($keyCache, function () use ($field) {
                return coll($this->select($field))->max($field);
            }, $this->age);
        }

        public function avg($field = 'id')
        {
            $this->query[] = ['avg' => $field];

            $keyCache = sha1('avg.' . $this->dir . $field . serialize($this->query));

            return $this->db->untilCache($keyCache, function () use ($field) {
                return coll($this->select($field))->avg($field);
            }, $this->age);
        }

        public function multisort($criteria)
        {
            $this->query[] = ['multisort' => serialize($criteria)];

            $keyCache = sha1('multisort.' . $this->dir . serialize($criteria) . serialize($this->query));

            $ids =  $this->db->untilCache($keyCache, function () use ($criteria) {
                $results = coll($this->select(array_keys($criteria)))->multisort($criteria);

                return array_values($results->fetch('id')->toArray());
            }, $this->age);

            $this->resource = Arrays::makeResource($ids);

            $this->count = count($this->getIterator());

            return $this;
        }

        public function groupBy($field)
        {
            $this->query[] = ['groupBy' => $field];

            $keyCache = sha1('groupBy.' . $this->dir . $field . serialize($this->query));

            $ids =  $this->db->untilCache($keyCache, function () use ($field) {
                $results = coll($this->select($field))->groupBy($field);

                return array_values($results->fetch('id')->toArray());
            }, $this->age);

            $this->resource = Arrays::makeResource($ids);

            $this->count = count($this->getIterator());

            return $this;
        }

        public function sortBy($field)
        {
            $this->query[] = ['sortBy' => $field];

            $keyCache = sha1('sortBy.' . $this->dir . $field . serialize($this->query));

            $ids =  $this->db->untilCache($keyCache, function () use ($field) {
                $results = coll($this->select($field))->sortBy($field);

                return array_values($results->fetch('id')->toArray());
            }, $this->age);

            $this->resource = Arrays::makeResource($ids);

            $this->count = count($this->getIterator());

            return $this;
        }

        public function sortByDesc($field)
        {
            $this->query[] = ['sortByDesc' => $field];

            $keyCache = sha1('sortByDesc.' . $this->dir . $field . serialize($this->query));

            $ids =  $this->db->untilCache($keyCache, function () use ($field) {
                $results = coll($this->select($field))->sortByDesc($field);

                return array_values($results->fetch('id')->toArray());
            }, $this->age);

            $this->resource = Arrays::makeResource($ids);

            $this->count = count($this->getIterator());

            return $this;
        }

        public function where($key, $operator = null, $value = null)
        {
            $this->query[] = func_get_args();

            if (func_num_args() == 1) {
                if (is_array($key)) {
                    if (count($key) == 1) {
                        $operator   = '=';
                        $value      = array_values($key);
                        $key        = array_keys($key);
                    } elseif (count($key) == 3) {
                        list($key, $operator, $value) = $key;
                    }

                    $operator = strtolower($operator);
                }
            }

            if (func_num_args() == 2) {
                list($value, $operator) = [$operator, '='];
            }

            $keyCache = sha1(serialize($this->query) . $this->dir . $key);

            $ids = $this->db->untilCache($keyCache, function () use ($key, $operator, $value) {
                $collection = coll($this->select($key));
                $results = $collection->filter(function($item) use ($key, $operator, $value) {
                    $item = (object) $item;
                    $actual = isset($item->{$key}) ? $item->{$key} : null;

                    $insensitive = in_array($operator, ['=i', 'like i', 'not like i']);

                    if ((!is_array($actual) || !is_object($actual)) && $insensitive) {
                        $actual = Inflector::lower(Inflector::unaccent($actual));
                    }

                    if ((!is_array($value) || !is_object($value)) && $insensitive) {
                        $value  = Inflector::lower(Inflector::unaccent($value));
                    }

                    if ($insensitive) {
                        $operator = str_replace(['=i', 'like i'], ['=', 'like'], $operator);
                    }

                    if ($key == 'id' || fnmatch('*_id', $key) && is_numeric($actual)) {
                        $actual = (int) $actual;
                    }

                    switch ($operator) {
                        case '<>':
                        case '!=':
                            return sha1(serialize($actual)) != sha1(serialize($value));
                        case '>':
                            return $actual > $value;
                        case '<':
                            return $actual < $value;
                        case '>=':
                            return $actual >= $value;
                        case '<=':
                            return $actual <= $value;
                        case 'between':
                            return $actual >= $value[0] && $actual <= $value[1];
                        case 'not between':
                            return $actual < $value[0] || $actual > $value[1];
                        case 'in':
                            return in_array($actual, $value);
                        case 'not in':
                            return !in_array($actual, $value);
                        case 'like':
                            $value  = str_replace("'", '', $value);
                            $value  = str_replace('%', '*', $value);

                            return fnmatch($value, $actual);
                        case 'not like':
                            $value  = str_replace("'", '', $value);
                            $value  = str_replace('%', '*', $value);

                            $check  = fnmatch($value, $actual);

                            return !$check;
                        case 'is':
                            return is_null($actual);
                        case 'is not':
                            return !is_null($actual);
                        case '=':
                        default:
                            return sha1(serialize($actual)) == sha1(serialize($value));
                    }
                });

                return array_values($results->fetch('id')->toArray());
            }, $this->age);

            $this->resource = Arrays::makeResource($ids);

            $this->count = count($this->getIterator());

            return $this;
        }

        public function toArray($fields = null)
        {
            $fields = is_null($fields) ? $this->fields() : $fields;

            if (!in_array('id', $fields)) {
                $fields[] = 'id';
            }

            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $cursor = (array) $cursor;

            return SplFixedArray::fromArray(array_map(function ($id) use ($fields) {
                $row = [];
                $row['id'] = $id;

                foreach ($fields as $field) {
                    $row[$field] = $this->getFieldValueById($field, $id);
                }

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                return $row;
            }, $cursor));
        }

        public function toModels($fields = null)
        {
            $fields = is_null($fields) ? $this->fields() : $fields;

            if (!in_array('id', $fields)) {
                $fields[] = 'id';
            }

            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $cursor = (array) $cursor;

            return SplFixedArray::fromArray(array_map(function ($id) use ($fields) {
                $row = [];
                $row['id'] = $id;

                foreach ($fields as $field) {
                    $row[$field] = $this->getFieldValueById($field, $id);
                }

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                return $this->db->model($row);
            }, $cursor));
        }

        public function get($object = false)
        {
            return $this;
        }

        public function models()
        {
            $this->hook(function ($row) {
                return $this->db->model($row);
            });

            return $this;
        }

        public function first($object = false, $fields = null)
        {
            $this->position = 0;
            $cursor = $this->getIterator();

            if (isset($cursor[$this->position])) {
                if (empty($fields)) {
                    $row = $this->db->getRow($cursor[$this->position]);
                } else {
                    if (!in_array('id', $fields)) {
                        $fields[] = 'id';
                    }

                    $fullrow = $this->db->getRow($cursor[$this->position]);
                    $row = [];
                    $row['id'] = isAke($fullrow, 'id');

                    foreach ($fields as $field) {
                        $row[$field] = isAke($fullrow, $field);
                    }
                }

                $id = isAke($row, 'id', false);

                if (!$id) {
                    return null;
                }

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                $return = $object ? $this->db->model($row) : $row;

                $this->reset();

                return $return;
            }

            return null;
        }

        public function last($object = false, $fields = null)
        {
            $this->position = $this->count - 1;
            $cursor = $this->getIterator();

            if (isset($cursor[$this->position])) {
                if (empty($fields)) {
                    $row = $this->db->getRow($cursor[$this->position]);
                } else {
                    if (!in_array('id', $fields)) {
                        $fields[] = 'id';
                    }

                    $fullrow = $this->db->getRow($cursor[$this->position]);
                    $row = [];
                    $row['id'] = isAke($fullrow, 'id');

                    foreach ($fields as $field) {
                        $row[$field] = isAke($fullrow, $field);
                    }
                }

                $id = isAke($row, 'id', false);

                if (!$id) {
                    return null;
                }

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                $return = $object ? $this->db->model($row) : $row;

                $this->reset();

                return $return;
            }

            return null;
        }

        public function splice($offset, $length = null, $replacement = [])
        {
            if (func_num_args() == 1) {
                return $this->new(array_splice((array) $this->getIterator(), $offset));
            }

            return $this->new(array_splice((array) $this->getIterator(), $offset, $length, $replacement));
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

        public function firstBy($field, $value)
        {
            return $this->where($field, '=', $value)->first();
        }

        public function lastBy($field, $value)
        {
            return $this->where($field, '=', $value)->last();
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
            $items = (array) $this->getIterator();

            if (!empty($items)) {
                shuffle($items);

                $row = current($items);

                return $this->db->getRow($row['id']);
            }

            return $default;
        }

        public function isBetween($field, $min, $max)
        {
            return $this->where($field, 'between', [$min, $max]);
        }

        public function isNotBetween($field, $min, $max)
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

        private function merge($data = [])
        {
            $merged = $ids = [];

            foreach ($data as $row) {
                $id = isAke($row, 'id', null);

                if ($id) {
                    if (!in_array($id, $ids)) {
                        $ids[] = $id;
                        $merged[] = $row;
                    }
                }
            }

            return $merged;
        }

        public function all()
        {
            return $this;
        }

        public function map(callable $callback, $fields = null)
        {
            $fields = is_null($fields) ? $this->fields() : $fields;
            $data   = $this->select($fields);

            $results = coll($data)->each($callback);

            $this->resource = Arrays::makeResource(
                array_values(
                    $results->fetch('id')->toArray()
                )
            );

            $this->count = count($this->getIterator());

            return $this;
        }

        public function filter(callable $callback, $fields = null)
        {
            $fields = is_null($fields) ? $this->fields() : $fields;
            $data   = $this->select($fields);

            $results = coll($data)->filter($callback);

            $this->resource = Arrays::makeResource(array_values($results->fetch('id')->toArray()));

            $this->count = count($this->getIterator());

            return $this;
        }

        public function fetch($field)
        {
            return $this->select($field);
        }

        public function paginate($page, $perPage)
        {
            $items = (array) $this->getIterator();

            return $this->new(array_slice($items, ($page - 1) * $perPage, $perPage));
        }

        public function delete()
        {
            $deleted = 0;

            $items = (array) $this->getIterator();

            foreach ($items as $item) {
                if (isset($item['id'])) {
                    $row = $this->db->find((int) $item['id']);

                    if ($row) {
                        $row->delete();
                        $deleted++;
                    }
                }
            }

            return $deleted;
        }

        public function update(array $criteria)
        {
            $updated = 0;

            $items = (array) $this->getIterator();

            foreach ($items as $item) {
                if (isset($item['id'])) {
                    $row = $this->db->find((int) $item['id']);

                    if ($row) {
                        foreach ($criteria as $k => $v) {
                            $setter = setter($k);
                            $row->$setter($v);
                        }

                        $row->save();
                        $updated++;
                    }
                }
            }

            return $updated;
        }

        public function __call($m, $a)
        {
            if ($m != 'or') {
                if ($m == 'and') {
                    return call_user_func_array([$this, 'where'], $a);
                } else {
                    if ($m == 'new') {
                        $this->resource = Arrays::makeResource(current($a));

                        $this->count = count($this->getIterator());

                        return $this;
                    } else {
                        $collection = coll($this->select());

                        $results = call_user_func_array([$collection, $m], $a);

                        if (is_object($results)) {
                            $this->resource = Arrays::makeResource(array_values($results->fetch('id')->toArray()));

                            $this->count = count($this->getIterator());

                            return $this;
                        } else {
                            return $results;
                        }
                    }
                }
            } else {
                $oldIds         = (array) $this->getIterator();
                $this->resource = Arrays::makeResource($this->ids());

                call_user_func_array([$this, 'where'], $a);

                $merged = array_merge($oldIds, (array) $this->getIterator());

                $this->resource = Arrays::makeResource(array_values($merged));

                $this->count = count($this->getIterator());

                return $this;
            }
        }

        public function fresh()
        {
            $this->age = -1;

            return $this;
        }

        public function noCache()
        {
            return $this->fresh();
        }

        public function exists()
        {
            if (!empty($this->query)) {
                return $this->count > 0;
            }

            return false;
        }

        public function hook(callable $closure)
        {
            $this->closure = $closure;

            return $this;
        }
    }

