<?php
    namespace Octo\Mongo;

    class None
    {
        private $db;

        public function __construct(Db $db)
        {
            $this->db = $db;
        }

        public function __destruct()
        {
            $this->db->reset();
        }

        public function __call($f, $a)
        {
            return $this;
        }

        public function count()
        {
            return 0;
        }

        public function min()
        {
            return 0;
        }

        public function max()
        {
            return 0;
        }

        public function sum()
        {
            return 0;
        }

        public function avg()
        {
            return 0;
        }

        public function first($object = false, $reset = true)
        {
            if (true === $reset) {
                $this->db->reset();
            }

            return true === $object ? null : [];
        }

        public function last($object = false, $reset = true)
        {
            if (true === $reset) {
                $this->db->reset();
            }

            return true === $object ? null : [];
        }

        public function exec($object = false, $count = false, $first = false)
        {
            if ($count) {
                return 0;
            }

            return true === $object ? new Collection([]) : [];
        }

        public function all($object = false)
        {
            return true === $object ? new Collection([]) : [];
        }

        public function fetch($object = false)
        {
            return $this->all($object);
        }

        public function findAll($object = true)
        {
            return $this->all($object);
        }

        public function getAll($object = false)
        {
            return $this->all($object);
        }

        public function run($object = false)
        {
            return $this->exec($object);
        }

        public function get($object = false)
        {
            return $this->exec($object);
        }

        public function execute($object = false)
        {
            return $this->exec($object);
        }
    }
