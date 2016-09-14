<?php
    namespace Octo\Mongo;

    class Orm
    {
        private $db;
        private $conditions = [];
        private $selects = [];

        public function __construct(Db $db)
        {
            $this->db = $db;
        }

        public function where(array $condition, $op = 'AND')
        {
            $condition[] = $op;
            $this->conditions[] = $condition;

            return $this;
        }

        public function select($field)
        {
            $this->selects[$field] = true;

            return $this;
        }

        public function get()
        {
            $db     = $this->db->cnx->selectDB(SITE_NAME);
            $coll   = $db->selectCollection($this->db->collection);

            $coll->ensureIndex(['id' => 1]);

            $query = $this->db->prepare($this->conditions);

            return new Cursor($coll->find($query, $this->selects), $this->db);
        }
    }
