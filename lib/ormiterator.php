<?php
    namespace Octo;

    use Countable;
    use Iterator;
    use Closure;

    class Ormiterator implements Countable, Iterator
    {
        protected $pdo;
        protected $values = [];
        protected $statement;
        protected $count;
        protected $entity;
        protected $item;
        protected $cursor   = 0;
        protected $hook     = 0;

        public function __construct($statement, $orm)
        {
            $this->statement    = $statement;
            $this->entity       = $orm->getEntity();
            $this->values       = $orm->values();
            $this->pdo          = $statement->reveal();
            $this->count        = $orm->count();
            $this->hook         = $orm->getHook();
        }

        public function count()
        {
            return $this->count;
        }

        public function __destruct()
        {
            $this->reset();
        }

        public function reset()
        {
            $this->cursor = 0;
            $this->item = null;
        }

        public function rewind()
        {
            $this->cursor = 0;
        }

        public function seek($pos = 0)
        {
            $this->cursor = $pos;

            return $this;
        }

        public function first($model = true)
        {
            $this->next();

            if ($model) {
                return $this->entity->model($this->item);
            } else {
                return $this->item;
            }
        }

        public function next()
        {
            $this->cursor++;
            $this->item = $this->statement->fetch();

            if ($this->hook && $hook instanceof Closure) {
                $hook = $this->hook;
                $this->item = $hook($this->item);
            }
        }

        public function current()
        {
            return $this->item;
        }

        public function key()
        {
            return $this->cursor;
        }

        public function valid()
        {
            return $this->cursor < $this->count();
        }

        public function collection()
        {
            return coll($this->statement->fetchAll());
        }

        public function pluck($field, $key = null)
        {
            return $this->collection()->pluck($field, $key);
        }

        public function getIterator()
        {
            return $this->statement;
        }
    }
