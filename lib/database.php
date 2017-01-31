<?php
    namespace Octo;

    class Database
    {
        private $db;

        public function __construct($db = null)
        {
            $db = is_null($db) ? Strings::lower(def('SITE_NAME', 'core')) : $db;

            $this->db = $db;
        }

        public function table($table)
        {
            return engine($this->db, $table);
        }

        public function from($table)
        {
            return $this->table($table);
        }
    }
