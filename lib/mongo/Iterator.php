<?php
    namespace Octo\Mongo;

    use Iterator;
    use Countable;
    use Octo\File;

    class Huge implements Iterator, Countable
    {
        private $db, $cursor, $position = 0, $count = 0, $ids = [];

        public function __construct(Db $db)
        {
            $this->position = 0;
            $this->db       = $db;

            $this->prepare();
        }

        public function rewind()
        {
            $this->position = 0;
        }

        public function current()
        {
            $file = $this->cursor . DS . $this->position . '.php';

            return File::exists($file) ? include($file) : null;
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
            $file = $this->cursor . DS . $this->position . '.php';

            return File::exists($file);
        }

        public function count()
        {
            return $this->count;
        }

        public function toArray()
        {
            $collection = [];

            $files = glob($this->cursor . DS . '*.php');

            foreach ($files as $file) {
                $collection[] = include($file);
            }

            return $collection;
        }

        public function toJson($option = JSON_PRETTY_PRINT)
        {
            $collection = [];

            $files = glob($this->cursor . DS . '*.php');

            foreach ($files as $file) {
                $collection[] = include($file);
            }

            return json_encode($collection, $option);
        }

        public function limit($limit, $offset = 0)
        {
            $hash = sha1($this->db->getHash() . 'limit' . serialize(func_get_args()));

            $cursor = $this->db->motor()->getPath() . DS . 'cursors' . DS . $hash;

            if (is_dir($cursor)) {
                $ageCursor  = filemtime($cursor . DS . '.');
                $ageDb      = $this->db->getAge();

                if ($ageDb < $ageCursor) {
                    $this->cursor = $cursor;

                    return $this;
                } else {
                    File::rmdir($cursor);
                }
            }

            File::mkdir($this->db->motor()->getPath() . DS . 'cursors');
            File::mkdir($this->db->motor()->getPath() . DS . 'cursors' . DS . $hash);

            $index = 0;

            for ($i = $offset; $i < $limit; $i++) {
                $file = $this->cursor . DS . $i . '.php';

                if (File::exists($file)) {
                    $newFile = $cursor . DS . $index . '.php';
                    $data = include($file);
                    File::put($newFile, "<?php\nreturn " . var_export($data, 1) . ';');
                    $index++;
                }
            }

            $this->cursor = $cursor;

            return $this;
        }

        public function sort($field, $direction = 'ASC')
        {
            $hash = sha1($this->db->getHash() . 'sort' . serialize(func_get_args()));

            $cursor = $this->db->motor()->getPath() . DS . 'cursors' . DS . $hash;

            if (is_dir($cursor)) {
                $ageCursor  = filemtime($cursor . DS . '.');
                $ageDb      = $this->db->getAge();

                if ($ageDb < $ageCursor) {
                    $this->cursor = $cursor;

                    return $this;
                } else {
                    File::rmdir($cursor);
                }
            }

            File::mkdir($this->db->motor()->getPath() . DS . 'cursors');
            File::mkdir($this->db->motor()->getPath() . DS . 'cursors' . DS . $hash);

            $collection = [];

            $files = glob($this->cursor . DS . '*.php', GLOB_NOSORT);

            foreach ($files as $file) {
                $data   = include($file);

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
                $id = isAke($row, 'id');
                $file = $cursor . DS . $index . '.php';
                $data = $this->db->motor()->read('datas.' . $id);
                File::put($file, "<?php\nreturn " . var_export($data, 1) . ';');
                $index++;
            }

            $this->cursor = $cursor;

            return $this;
        }

        public function groupBy($field)
        {
            $hash = sha1($this->db->getHash() . 'groupby' . serialize(func_get_args()));

            $cursor = $this->db->motor()->getPath() . DS . 'cursors' . DS . $hash;

            if (is_dir($cursor)) {
                $ageCursor  = filemtime($cursor . DS . '.');
                $ageDb      = $this->db->getAge();

                if ($ageDb < $ageCursor) {
                    $this->cursor = $cursor;

                    return $this;
                } else {
                    File::rmdir($cursor);
                }
            }

            File::mkdir($this->db->motor()->getPath() . DS . 'cursors');
            File::mkdir($this->db->motor()->getPath() . DS . 'cursors' . DS . $hash);

            $collection = [];

            $files = glob($this->cursor . DS . '*.php', GLOB_NOSORT);

            foreach ($files as $file) {
                $data   = include($file);

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
                $file   = $cursor . DS . $index . '.php';
                $data   = $this->db->motor()->read('datas.' . $id);
                File::put($file, "<?php\nreturn " . var_export($data, 1) . ';');
                $index++;
            }

            $this->cursor = $cursor;

            return $this;
        }

        private function prepare()
        {
            $results        = $collection = [];
            $hash           = $this->db->getHash();

            $this->cursor   = $this->db->motor()->getPath() . DS . 'cursors' . DS . $hash;

            if (is_dir($this->cursor)) {
                $ageCursor  = filemtime($this->cursor . DS . '.');
                $ageDb      = $this->db->getAge();

                if ($ageDb < $ageCursor) {
                    $this->count = count(glob($this->cursor . DS . '*.php', GLOB_NOSORT));

                    return;
                } else {
                    File::rmdir($this->cursor);
                }
            }

            File::mkdir($this->db->motor()->getPath() . DS . 'cursors');
            File::mkdir($this->db->motor()->getPath() . DS . 'cursors' . DS . $hash);

            if (empty($this->db->wheres)) {
                $ids = $this->db->motor()->ids('datas');

                foreach ($ids as $id) {
                    $results[$id] = [];
                }

                unset($ids);
            } else {
                $results = $this->db->results;
            }

            $this->count = count($results);

            if (empty($results)) {
                File::rmdir($this->cursor);

                return true;
            } else {
                $index = 0;

                foreach ($results as $id => $row) {
                    if (false !== $id) {
                        $file = $this->cursor . DS . $index . '.php';
                        $data = $this->db->motor()->read('datas.' . $id);
                        File::put($file, "<?php\nreturn " . var_export($data, 1) . ';');
                        $index++;
                    }
                }
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
    }
