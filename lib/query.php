<?php
    namespace Octo;

    class Query
    {
        private $database, $table, $db, $selected = ['id'];

        public function __construct($database = null)
        {
            $database = is_null($database) ? def('SITE_NAME', 'core') : $database;

            $this->database = $database;
        }

        public function from($table)
        {
            $this->table = $table;
        }

        public function __call($m, $a)
        {
            if (!strlen($this->table)) {
                throw new Exception("You must select a table with the from method.");
            }

            if (!$this->db instanceof Octalia) {
                $this->db = odb($this->database, $this->table);
            } else {
                return call_user_func_array([$this->db, $m], $a);
            }
        }

        public function get()
        {
            if (!strlen($this->table)) {
                throw new Exception("You must select a table with the from method.");
            }

            if (!$this->db instanceof Octalia) {
                $this->db = odb($this->database, $this->table);
            } else {
                return $this->db->get()->hook(function($row) {
                    $return = [];

                    foreach ($this->selected as $field) {
                        $return[$field] = isAke($row, $field, null);
                    }

                    return $return;
                });
            }
        }

        public function select($fields)
        {
            if (func_num_args() > 1) {
                $fields = func_get_args();
            } else {
                if (is_string($fields)) {
                    if (fnmatch('*, *', $fields) || fnmatch('* ,*', $fields)) {
                        $fields = str_replace([' ,', ', '], ',', $fields);
                    }

                    $fields = explode(',', $fields);
                }
            }

            if (is_array($fields)) {
                foreach ($fields as $field) {
                    if (!in_array($field, $this->selected)) {
                        $this->selected[] = $field;
                    }
                }
            }

            return $this;
        }
    }
