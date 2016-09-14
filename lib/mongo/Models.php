<?php
    namespace Octo\Mongo;

    use Iterator;
    use Countable;
    use Thin\Now;

    class Models implements Countable, Iterator
    {
        private $collection, $closure;

        public function __construct(Db $db, $closure = null)
        {
            $this->collection = 'models.results.redis.' . $db->db . '.' . $db->table;
            $results  = new Results($db);
            Now::set($this->collection, $results);
            $this->closure  = $closure;
        }

        public function __get($k)
        {
            if ($k == 'results') {
                return Now::get($this->collection);
            }

            return $this->$k;
        }

        public function count()
        {
            return $this->getIterator()->count();
        }

        public function current()
        {
            $current = $this->getIterator()->current();

            unset($current['_id']);

            $current = $this->checkGlue($current);

            $current = $this->getDb()->model($current);

            if (is_callable($this->closure)) {
                $callable = $this->closure;
                $current = $callable($current);
            }

            return $current;
        }

        public function __call($m, $a)
        {
            return call_user_func_array([$this->results, $m], $a);
        }

        public function rewind()
        {
            $this->getIterator()->rewind();
        }

        public function first()
        {
            $this->rewind();

            return $this->current();
        }

        public function key()
        {
            return $this->getIterator()->key();
        }

        public function next()
        {
            $this->getIterator()->next();
        }

        public function valid()
        {
            return $this->getIterator()->valid();
        }

        public function each(callable $closure)
        {
            $row = $this->getIterator()->getNext();

            if ($row) {
                $row = $this->getDb()->model($row);

                return $closure($row);
            }

            return false;
        }

        public function update(array $data)
        {
            while ($row = $this->getIterator()->getNext()) {
                if ($row) {
                    unset($row['_id']);
                    $row = $this->getDb()->model($row);

                    foreach ($data as $k => $v) {
                        $row->$k = $v;
                    }

                    $row->save();
                }
            }

            return $this->count();
        }

        public function delete()
        {
            while ($row = $this->getIterator()->getNext()) {
                if ($row) {
                    unset($row['_id']);
                    $row = $this->getDb()->model($row);
                    $row->delete();
                }
            }

            return $this->count();
        }
    }
