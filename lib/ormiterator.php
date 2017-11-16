<?php
    namespace Octo;

    use Closure;
    use Countable;
    use Iterator;
    use PDO;

    class Ormiterator implements Countable, Iterator
    {
        protected $pdo;
        protected $instance;
        protected $orm;
        protected $values = [];
        protected $statement;
        protected $count;
        protected $entity;
        protected $item;
        protected $cursor   = 0;
        protected $hook     = 0;

        public function __construct($statement, Orm $orm)
        {
            $this->statement    = $statement;
            $this->entity       = $orm->getEntity();
            $this->values       = $orm->values();
            $this->pdo          = $statement->reveal();
            $this->hook         = $orm->getHook();

            $this->instance     = hash(token());

            $this->set('orm', $orm);
        }

        /**
         * @param $key
         * @param $value
         * @return $this
         */
        public function set($key, $value)
        {
            $k = $this->instance . $key;

            Registry::set($k, $value);

            return $this;
        }

        /**
         * @param $key
         * @param null $default
         *
         * @return mixed
         */
        public function get($key, $default = null)
        {
            $k = $this->instance . $key;

            return Registry::get($k, $default);
        }

        /**
         * @return int
         */
        public function count()
        {
            if (is_null($this->count)) {
                $this->count = $this->get('orm')->count();
            }

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

        /**
         * @param int $pos
         * @return $this
         */
        public function seek($pos = 0)
        {
            $this->cursor = $pos;

            return $this;
        }

        /**
         * @param bool $model
         *
         * @return mixed|Record
         */
        public function first($model = true)
        {
            $this->next();

            if ($model) {
                return $this->getEntity()->model($this->item);
            } else {
                return $this->getItem();
            }
        }

        public function next()
        {
            $this->cursor++;
            
            $this->item = $this->getStatement()->fetch();
            
            $hook = $this->hook;

            if ($hook && $hook instanceof Closure) {
                $this->item = $hook($this->getItem());
            }
        }

        /**
         * @return mixed
         */
        public function current()
        {
            return $this->getItem();
        }

        /**
         * @return int
         */
        public function key()
        {
            return $this->getCursor();
        }

        /**
         * @return bool
         */
        public function valid()
        {
            return $this->getCursor() < $this->count();
        }

        /**
         * @return Collection
         */
        public function collection()
        {
            return coll($this->statement->fetchAll());
        }

        /**
         * @param $field
         * @param null $key
         * @return array
         */
        public function pluck($field, $key = null)
        {
            return $this->collection()->pluck($field, $key);
        }

        /**
         * @return mixed
         */
        public function getIterator()
        {
            return $this->getStatement();
        }

        /**
         * @return Entity
         */
        public function getEntity()
        {
            return $this->entity;
        }

        /**
         * @return PDO
         */
        public function getPdo()
        {
            return $this->pdo;
        }

        /**
         * @return mixed
         */
        public function getStatement()
        {
            return $this->statement;
        }

        /**
         * @return mixed
         */
        public function getItem()
        {
            return $this->item;
        }

        /**
         * @return int
         */
        public function getCursor()
        {
            return $this->cursor;
        }

        /**
         * @return string
         */
        public function getInstance()
        {
            return $this->instance;
        }
    }
