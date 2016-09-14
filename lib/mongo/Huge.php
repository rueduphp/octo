<?php
    namespace Octo\Mongo;

    class Huge implements \Iterator, \Countable
    {
        private $clientCache, $db, $cursor, $position = 0, $count = 0, $ids = [];

        public function __construct(Db $db)
        {
            $this->position = 0;
            $this->db       = $db;

            // $this->prepare();
        }

        public function rewind()
        {
            $this->position = 0;
        }

        public function current()
        {
            $file = $this->cursor . '.' . $this->position;

            return $this->cache()->exists($file) ? $this->import($file) : null;
        }

        public function key()
        {
            return $this->position;
        }

        public function next()
        {
            ++$this->position;
        }

        public function previous()
        {
            --$this->position;
        }

        public function valid()
        {
            $file = $this->cursor . '.' . $this->position;

            return $this->cache()->exists($file);
        }

        public function count()
        {
            return $this->count;
        }

        public function toArray()
        {
            $collection = [];

            $files = $this->cache()->keys($this->cursor . '.*');

            foreach ($files as $file) {
                $collection[] = $this->import($file);
            }

            return $collection;
        }

        public function toJson()
        {
            $collection = [];

            $files = $this->cache()->keys($this->cursor . '.*');

            foreach ($files as $file) {
                $collection[] = $this->import($file);
            }

            return json_encode($collection);
        }

        public function limit($limit, $offset = 0)
        {
            $hash = sha1($this->db->getHash() . 'limit' . serialize(func_get_args()));

            $cursor = $this->motor()->getPath() . '.cursors.' . $hash;
            $rows   = $this->cache()->keys($cursor . '.*');
            $count  = count($rows);

            if ($count > 0) {
                $ageCursor  = $this->cache()->get($cursor . '_age');
                $ageDb      = $this->db->getAge();

                $ageCursor = empty($ageCursor) ? 0 : (int) $ageCursor;

                if ($ageDb < $ageCursor) {
                    $this->cursor = $cursor;

                    return $this;
                } else {
                    foreach ($rows as $row) {
                        $this->cache()->del($row);
                    }
                }
            }

            $index = 0;

            for ($i = $offset; $i < $limit; $i++) {
                $file = $this->cursor . '.' . $i;

                if ($this->cache()->exists($file)) {
                    $newFile = $cursor . '.' . $index;
                    $data = $this->import($file);
                    $this->cache()->set($newFile, "return " . var_export($data, 1) . ';');
                    $index++;
                }
            }

            $this->cursor = $cursor;

            $this->cache()->set($cursor . '_age', time());

            return $this;
        }

        public function sort($field, $direction = 'ASC')
        {
            $hash = sha1($this->db->getHash() . 'sort' . serialize(func_get_args()));

            $cursor = $this->motor()->getPath() . '.cursors.' . $hash;
            $rows   = $this->cache()->keys($cursor . '.*');
            $count  = count($rows);

            if ($count > 0) {
                $ageCursor  = $this->cache()->get($cursor . '_age');
                $ageDb      = $this->db->getAge();

                $ageCursor = empty($ageCursor) ? 0 : (int) $ageCursor;

                if ($ageDb < $ageCursor) {
                    $this->cursor = $cursor;

                    return $this;
                } else {
                    foreach ($rows as $row) {
                        $this->cache()->del($row);
                    }
                }
            }

            $collection = [];

            $files = $this->cache()->keys($this->cursor . '.*');

            foreach ($files as $file) {
                $data   = $this->import($file);

                $id     = isAke($data, 'id');
                $val    = isAke($data, $field);

                $row    = ['id' => $id, $field => $val];

                $collection[] = $row;
            }

            $collection = lib('collection', [$collection]);

            if ($direction == 'ASC') {
                $collection->sortBy($field);
            } else {
                $collection->sortByDesc($field);
            }

            $index = 0;

            foreach ($collection as $row) {
                $id     = isAke($row, 'id');
                $file   = $cursor . '.' . $index;
                $data   = $this->motor()->read('datas.' . $id);
                $this->cache()->set($file, "return " . var_export($data, 1) . ';');
                $index++;
            }

            $this->cursor = $cursor;
            $this->cache()->set($cursor . '_age', time());

            return $this;
        }

        public function groupBy($field)
        {
            $hash = sha1($this->db->getHash() . 'groupby' . serialize(func_get_args()));

            $cursor = $this->motor()->getPath() . '.cursors.' . $hash;
            $rows   = $this->cache()->keys($cursor . '.*');
            $count  = count($rows);

            if ($count > 0) {
                $ageCursor  = $this->cache()->get($cursor . '_age');
                $ageDb      = $this->db->getAge();

                $ageCursor = empty($ageCursor) ? 0 : (int) $ageCursor;

                if ($ageDb < $ageCursor) {
                    $this->cursor = $cursor;

                    return $this;
                } else {
                    foreach ($rows as $row) {
                        $this->cache()->del($row);
                    }
                }
            }

            $collection = [];

            $files = $this->cache()->keys($this->cursor . '.*');

            foreach ($files as $file) {
                $data   = $this->import($file);

                $id     = isAke($data, 'id');
                $val    = isAke($data, $field);

                $row    = ['id' => $id, $field => $val];

                $collection[] = $row;
            }

            $collection = lib('collection', [$collection]);

            $collection->groupBy($field);

            $index = 0;

            foreach ($collection as $row) {
                $id     = isAke($row, 'id');
                $file   = $cursor . '.' . $index;
                $data   = $this->motor()->read('datas.' . $id);
                $this->cache()->set($file, "return " . var_export($data, 1) . ';');
                $index++;
            }

            $this->cursor = $cursor;
            $this->cache()->set($cursor . '_age', time());

            return $this;
        }

        public function import($file)
        {
            $content = $this->cache()->get($file);

            if (!$content) {
                $content = 'return null;';
            }

            return eval($content);
        }

        private function prepare()
        {
            $results        = $collection = [];
            $hash           = $this->db->getHash();

            $this->cursor   = $this->motor()->getPath() . '.cursors.' . $hash;
            $rows           = $this->cache()->keys($this->cursor . '.*');
            $count          = count($rows);

            if ($count > 0) {
                $ageCursor  = $this->cache()->get($this->cursor . '_age');
                $ageDb      = $this->db->getAge();

                $ageCursor = empty($ageCursor) ? 0 : (int) $ageCursor;

                if ($ageDb < $ageCursor) {
                    $this->count = $count;

                    return;
                } else {
                    foreach ($rows as $row) {
                        $this->cache()->del($row);
                    }
                }
            }

            if (empty($this->db->wheres)) {
                $ids = $this->motor()->ids('datas');

                foreach ($ids as $id) {
                    $results[$id] = [];
                }

                unset($ids);
            } else {
                $results = $this->db->results;
            }

            $this->count = count($results);

            if (empty($results)) {
                return true;
            } else {
                $index = 0;

                foreach ($results as $id => $row) {
                    if (false !== $id) {
                        $file = $this->cursor . '.' . $index;
                        $data = $this->motor()->read('datas.' . $id);
                        $this->cache()->set($file, "return " . var_export($data, 1) . ';');
                        $index++;
                    }
                }

                $this->cache()->set($this->cursor . '_age', time());
            }
        }

        public function push($index, $row)
        {
            $id             = isAke($row, 'id', false);
            $hash           = $this->db->getHash();
            $this->cursor   = $this->motor()->getPath() . '.cursors.' . $hash;

            if (false !== $id) {
                $file = $this->cursor . '.' . $index;
                $this->cache()->set($file, "return " . var_export($row, 1) . ';');
                $this->count++;
            }
        }

        public function fetch($object = false)
        {
            $row = $this->current();

            $this->next();

            $id = isAke($row, 'id', false);

            if (!$id) {
                return false;
            }

            return $object ? $this->db->model($row) : $row;
        }

        public function model()
        {
            $row = $this->current();

            $this->next();

            $id = isAke($row, 'id', false);

            return false !== $id ? $this->db->model($row) : false;
        }

        public function first($object = false)
        {
            $row = $this->current();

            $this->rewind();

            $id = isAke($row, 'id', false);

            if (!$id) {
                return null;
            }

            return $object ? $this->db->model($row) : $row;
        }

        public function last($object = false)
        {
            $row = $this->clue($this->count - 1);

            $this->rewind();

            $id = isAke($row, 'id', false);

            if (!$id) {
                return null;
            }

            return $object ? $this->db->model($row) : $row;
        }

        public function clue($id)
        {
            $file = $this->cursor . '.' . $id;

            return $this->cache()->exists($file) ? $this->import($file) : null;
        }

        public function cache()
        {
            if (is_null($this->clientCache)) {
                $this->clientCache =  lib('redys', ['motor.' . $this->db->collection]);
            }

            return $this->clientCache;
        }

        public function motor()
        {
            return new Motor($this->db->collection);
        }
    }
