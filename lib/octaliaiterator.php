<?php
    namespace Octo;

    use Countable;
    use Iterator;

    class OctaliaIterator implements Countable, Iterator
    {
        private $database, $table, $directory;

        public function __construct($db, callable $closure = null)
        {
            $key = 'db.' . $db->db . '.' . $db->table . '.' . sha1($db->driver->getDirectory());
            Registry::set($key, $db);

            $key = 'position.' . $db->db . '.' . $db->table . '.' . sha1($db->driver->getDirectory());
            Registry::set($key, 0);

            $key = 'closure.' . $db->db . '.' . $db->table . '.' . sha1($db->driver->getDirectory());
            Registry::set($key, $closure);

            $this->database = $db->db;
            $this->table = $db->table;
            $this->directory = $db->driver->getDirectory();
        }

        public function __get($k)
        {
            if ($k == 'ids') {
                $key = 'ids.' . $this->database . '.' . $this->db->table . '.' . sha1($this->directory);

                if (!isset($this->ids)) {
                    $ids = $this->db->iterator();
                    Registry::set($key, $ids);
                } else {
                    $ids = Registry::get($key);
                }

                return $ids;
            } elseif ('db' == $k) {
                $key = 'db.' . $this->database . '.' . $this->table . '.' . sha1($this->directory);

                return Registry::get($key);
            } elseif ('position' == $k) {
                $key = 'position.' . $this->database . '.' . $this->table . '.' . sha1($this->directory);

                return Registry::get($key);
            } elseif ('closure' == $k) {
                $key = 'closure.' . $this->database . '.' . $this->table . '.' . sha1($this->directory);

                return Registry::get($key);
            } elseif ('count' == $k) {
                $key = 'count.' . $this->database . '.' . $this->table . '.' . sha1($this->directory);

                return Registry::get($key);
            }

            return $this->$k;
        }

        public function __set($k, $v)
        {
            if ($k == 'ids') {
                $key = 'ids.' . $this->database . '.' . $this->table . '.' . sha1($this->directory);
                Registry::set($key, $v);
            } elseif ($k == 'position') {
                $key = 'position.' . $this->database . '.' . $this->table . '.' . sha1($this->directory);
                Registry::set($key, $v);
            } elseif ($k == 'db') {
                $key = 'db.' . $this->database . '.' . $this->table . '.' . sha1($this->directory);
                Registry::set($key, $v);
            } elseif ($k == 'closure') {
                $key = 'closure.' . $this->database . '.' . $this->table . '.' . sha1($this->directory);
                Registry::set($key, $v);
            } elseif ($k == 'count') {
                $key = 'count.' . $this->database . '.' . $this->table . '.' . sha1($this->directory);
                Registry::set($key, $v);
            } else {
                $this->$k = $v;
            }
        }

        public function __destruct()
        {
            $this->reset();
        }

        public function reset()
        {
            $this->position = 0;
            $this->count    = 0;
            $this->closure  = null;

            return $this;
        }

        public function getIterator()
        {
            return $this->ids;
        }

        public function count($return = true)
        {
            if (!isset($this->count) || is_null($this->count)) {
                $this->count = count($this->getIterator());
            }

            return $return ? $this->count : $this;
        }

        public function getNext()
        {
            $cursor = $this->getIterator();

            if (isset($cursor[$this->position])) {
                $row = $this->db->find($cursor[$this->position], false);

                $this->position++;

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                return $this->db->read($row);
            }

            return false;
        }

        public function getPrev()
        {
            $cursor = $this->getIterator();
            $this->position--;

            if (isset($cursor[$this->position])) {
                $row = $this->db->find($cursor[$this->position], false);

                $this->position++;

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                return $this->db->read($row);
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
                $row = $this->db->find($cursor[$this->position], false);

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                $row = $this->db->read($row);

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

        public function first($model = false)
        {
            $id = current($this->getIterator());

            $row = $this->db->row($id);

            $row = $this->db->read($row);

            if (is_callable($this->closure)) {
                $row = call_user_func_array($this->closure, [$row]);
            }

            return $model ? $this->db->model($row) : $row;
        }

        public function last($model = false)
        {
            $i  =  $this->getIterator();
            $id = end($i);

            $row = $this->db->row($id);
            $row = $this->db->read($row);

            if (is_callable($this->closure)) {
                $row = call_user_func_array($this->closure, [$row]);
            }

            return $model ? $this->db->model($row) : $row;
        }

        public function model()
        {
            $this->hook(function ($row) {
                return $this->db->model($row);
            });

            return $this;
        }

        public function toArray()
        {
            $collection = [];

            foreach ($this->getIterator() as $id) {
                $collection[] = $this->db->read($this->db->row($id));
            }

            return $collection;
        }

        public function repository()
        {
            return coll($this->toArray());
        }

        public function toModel()
        {
            $collection = [];

            foreach ($this->getIterator() as $id) {
                $collection[] = $this->db->find($id);
            }

            return $collection;
        }

        public function toJson()
        {
            return json_encode($this->toArray());
        }

        public function hook(callable $closure)
        {
            $this->closure = $closure;

            return $this;
        }

        function update(array $criteria)
        {
            return $this->db->update($criteria);
        }

        function delete()
        {
            $affected = 0;

            foreach ($this->getIterator() as $id) {
                $this->db->delete($id);

                $affected++;
            }

            return $affected;
        }

        public function table()
        {
            return $this->table;
        }

        public function database()
        {
            return $this->database;
        }

        public function directory()
        {
            return $this->directory;
        }
    }
