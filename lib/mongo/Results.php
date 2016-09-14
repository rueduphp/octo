<?php
    namespace Octo\Mongo;

    use Iterator;

    class Results implements \Countable, \IteratorAggregate
    {
        private $object = false, $db, $wheres, $cursor, $orders, $selects, $offset, $limit, $position = 0, $glues = [];

        public function __construct(Db $db, $object = false)
        {
            $this->db       = $db;
            $this->wheres   = $db->wheres;
            $this->orders   = $db->orders;
            $this->selects  = $db->selects;
            $this->offset   = $db->offset;
            $this->limit    = $db->limit;
            $this->object   = $object;

            $this->cursor();
        }

        public function getDb()
        {
            return $this->db;
        }

        public function __destruct()
        {
            $this->reset();
        }

        public function glue($field)
        {
            if (!in_array($field, $this->glues)) {
                $this->glues[] = $field;
            }

            return $this;
        }

        public function count($return = false)
        {
            $count = $this->cursor->count();

            if (!$return) {
                $this->reset();
            }

            return $count;
        }

        public function getNext()
        {
            $next = $this->cursor->getNext();

            unset($next['_id']);

            $this->position++;

            return $this->checkGlue($next);
        }

        public function current()
        {
            $current = $this->cursor->current();

            unset($current['_id']);

            return $this->checkGlue($current);
        }

        public function checkGlue($row)
        {
            if (!empty($this->glues)) {
                foreach ($this->glues as $field) {
                    $one = isAke($row, $field . '_id', false);

                    if ($one) {
                        $data = Db::instance($this->db->db, $field)->find((int) $one, false);
                        $row[$field] = $data;
                    } else {
                        if ($field[strlen($field) - 1] == 's' && isset($row['id']) && $field[0] != '_') {
                            $db = Db::instance($this->db->db, substr($field, 0, -1));

                            $idField = $this->db->table . '_id';

                            $row[$field] = $db->where([$idField, '=', (int) $row['id']])->cursor()->toArray();
                        }
                    }
                }
            }

            return $row;
        }

        public function getIterator()
        {
            return $this->cursor;
        }

        public function groupBy($field)
        {
            $collection = [];

            foreach ($this->cursor as $row) {
                $id     = isAke($row, 'id');
                $val    = isAke($row, $field);

                if ($id && $val) {
                    $add = ['id' => $id, $field => $val];
                    $collection[] = $add;
                }
            }

            $results = [];

            foreach ($collection as $row) {
                $results[$row['id']] = $row[$field];
            }

            $results = array_unique($results);

            asort($results);

            return array_values($results);
        }

        public function sum($field)
        {
            $collection = [];

            foreach ($this->cursor as $row) {
                $id     = isAke($row, 'id');
                $val    = isAke($row, $field);

                $add    = ['id' => $id, $field => $val];

                $collection[] = $add;
            }

            $collection = lib('collection', [$collection]);

            return $collection->sum($field);
        }

        public function avg($field)
        {
            $collection = [];

            foreach ($this->cursor as $row) {
                $id     = isAke($row, 'id');
                $val    = isAke($row, $field);

                $add    = ['id' => $id, $field => $val];

                $collection[] = $add;
            }

            $collection = lib('collection', [$collection]);

            return $collection->avg($field);
        }

        public function min($field)
        {
            $collection = [];

            foreach ($this->cursor as $row) {
                $id     = isAke($row, 'id');
                $val    = isAke($row, $field);

                $add    = ['id' => $id, $field => $val];

                $collection[] = $add;
            }

            $collection = lib('collection', [$collection]);

            return $collection->min($field);
        }

        public function max($field)
        {
            $collection = [];

            foreach ($this->cursor as $row) {
                $id     = isAke($row, 'id');
                $val    = isAke($row, $field);

                $add    = ['id' => $id, $field => $val];

                $collection[] = $add;
            }

            $collection = lib('collection', [$collection]);

            return $collection->max($field);
        }

        public function toArray()
        {
            $rows = iterator_to_array($this->cursor);

            $db = $this;

            return array_values(array_map(function ($row) use ($db) {
                unset($row['_id']);

                return $db->checkGlue($row);
            }, $rows));
        }

        public function toJson()
        {
            return json_encode($this->toArray());
        }

        public function hasNext()
        {
            return $this->cursor->hasNext();
        }

        public function info()
        {
            return $this->cursor->info();
        }

        public function explain()
        {
            return $this->cursor->explain();
        }

        public function fetch($object = false)
        {
            $row = $this->getNext();

            if ($row) {
                return $object ? $this->db->model($row) : $row;
            }

            $this->reset();

            return false;
        }

        public function model()
        {
            $row = $this->getNext();

             if ($row) {
                $id = isAke($row, 'id', false);

                return false !== $id ? $this->db->model($row) : false;
            }

            $this->reset();

            return false;
        }

        public function first($object = false)
        {
            $count = $this->count();

            if (0 == $count) {
                return null;
            }

            foreach ($this->cursor as $row) {
                unset($row['_id']);
                break;
            }

            $id = isAke($row, 'id', false);

            if (!$id) {
                return null;
            }

            $this->reset();

            return $object ? $this->db->model($row) : $row;
        }

        public function last($object = false)
        {
            $count = $this->count();

            if (0 == $count) {
                return null;
            }

            foreach ($this->cursor as $row) {
                unset($row['_id']);
            }

            $id = isAke($row, 'id', false);

            if (!$id) {
                return null;
            }

            return $object ? $this->db->model($row) : $row;
        }

        public function cursor()
        {
            if (!isset($this->cursor)) {
                $db = $this->getCollection();
                $db->ensureIndex(['id' => 1]);

                if (!empty($this->selects)) {
                    $fields = [];

                    foreach ($this->selects as $f) {
                        $fields[$f] = true;
                    }

                    $hasId = isAke($fields, 'id', false);

                    if (false === $hasId) {
                        $fields['id'] = true;
                    }
                }

                if (!empty($this->wheres)) {
                    $filter = $this->prepare($this->wheres, true);

                    if (!empty($this->selects)) {
                        $this->cursor = $db->find($filter, $fields);
                    } else {
                        $this->cursor = $db->find($filter);
                    }
                } else {
                    if (!empty($this->selects)) {
                        $this->cursor = $db->find([], $fields);
                    } else {
                        $this->cursor = $db->find();
                    }
                }

                if (!empty($this->orders)) {
                    $this->cursor->sort($this->orders);
                }

                if (isset($this->offset)) {
                    $this->cursor->skip($this->offset);
                }

                if (isset($this->limit)) {
                    $this->cursor->limit($this->limit);
                }
            }
        }

        public function __call($m, $a)
        {
            return call_user_func_array([$this->db, $m], $a);
        }

        public function update(array $data)
        {
            foreach ($this->cursor as $row) {
                $id = isAke($row, 'id', false);

                if (false !== $id) {
                    $obj = $this->db->find((int) $id);

                    foreach ($data as $k => $v) {
                        $obj->$k = $v;
                    }

                    $obj->save();
                }
            }

            return $this;
        }

        public function delete()
        {
            foreach ($this->cursor as $row) {
                $id = isAke($row, 'id', false);

                if (false !== $id) {
                    $obj = $this->db->find((int) $id);
                }

                $obj->delete();
            }

            return $this;
        }
    }
