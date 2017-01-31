<?php
    namespace Octo;

    class Mysql
    {
        private $server = "";
        private $user = "";
        private $pass = "";
        private $database = "";
        public $error = "";
        public $errno = 0;
        protected $affected_rows = 0;
        protected $query_counter = 0;
        protected $link_id = 0;
        protected $query_id = 0;
        protected $query_show;

        /**
         * Database::__construct()
         *
         * @param mixed $server
         * @param mixed $user
         * @param mixed $pass
         * @param mixed $database
         * @return
         */
        function __construct($server = null, $user = null, $password = null, $database = null)
        {
            $server = is_null($server)
            ? Config::get('mysql.server', 'localhost')
            : $server;

            $user = is_null($user)
            ? Config::get('mysql.user', 'root')
            : $user;

            $password = is_null($password)
            ? Config::get('mysql.password', 'root')
            : $password;

            $database = is_null($database)
            ? Config::get('mysql.database', def('SITE_NAME'))
            : $database;

            $this->server   = $server;
            $this->user     = $user;
            $this->pass     = $password;
            $this->database = $database;
        }

        /**
         * Database::connect()
         * Connect and select database using vars above
         * @return
         */
        public function connect()
        {
            $this->link_id = $this->connect_db($this->server, $this->user, $this->pass);

            if (!$this->link_id) {
                exception("mysql", "Connection to Database " . $this->database . " Failed.");
            }

            if (!$this->select_db($this->database, $this->link_id)) {
                exception("mysql", "SQL database (" . $this->database . ")cannot be used.");
            }

            mysqli_set_charset($this->link_id, "utf8");

            unset($this->password);
        }

        /**
         * Database::connect_db()
         *
         * @param mixed $server
         * @param mixed $user
         * @param mixed $pass
         * @return
         */
        private function connect_db($server, $user, $pass)
        {
            return mysqli_connect($server, $user, $pass);
        }

        /**
         * Database::select_db()
         *
         * @param mixed $database
         * @param mixed $link_id
         * @return
         */
        private function select_db($database, $link_id)
        {
            return mysqli_select_db($link_id, $database);
        }

        /**
         * Database::query()
         * Executes SQL query to an open connection
         * @param mixed $sql
         * @return (query_id)
         */
        public function query($sql)
        {
            if (trim($sql != "")) {
                $this->query_counter++;
                $this->query_show .= stripslashes($sql) . "<hr size='1' />";
                $this->query_id = mysqli_query($this->link_id, $sql);

                $this->last_query = $sql . '<br />';
            }

            if (!$this->query_id) {
                throw new Exception("mySQL Error on Query : " . $sql);
            }

            return $this->query_id;

        }

        /**
         * Database::first()
         * Fetches the first row only, frees resultset
         * @param mixed $string
         * @param bool $type
         * @return array
         */
        public function first($string, $type = false)
        {
            $query_id = $this->query($string);
            $record = $this->fetch($query_id, $type);
            $this->free($query_id);

            return $record;
        }

        /**
         * Database::fetch()
         * Fetches and returns results one line at a time
         * @param integer $query_id
         * @param bool $type
         * @return array
         */
        public function fetch($query_id, $type = false)
        {
            if ($query_id) $this->query_id = $query_id;

            if (isset($this->query_id)) {
                $record = ($type)
                ? mysqli_fetch_array($this->query_id, MYSQL_ASSOC)
                : mysqli_fetch_object($this->query_id);
            } else $this->error("Invalid query_id: <b>" . $this->query_id . "</b>. Records could not be fetched.");

            return $record;
        }

        /**
         * Database::fetch_all()
         * Returns all the results
         * @param mixed $sql
         * @param bool $type
         * @return assoc array
         */
        public function fetch_all($sql, $type = false)
        {
            $query_id = $this->query($sql);
            $record = array();

            while ($row = $this->fetch($query_id, $type)) $record[] = $row;

            $this->free($query_id);

            return $record;
        }

        /**
         * Database::free()
         * Frees the resultset
         * @param integer $query_id
         * @return query_id
         */
        private function free($query_id)
        {
            if ($query_id)  $this->query_id = $query_id;

            return mysqli_free_result($this->query_id);
        }

        /**
         * Database::insert()
         * Insert query with an array
         * @param mixed $table
         * @param mixed $data
         * @return id of inserted record, false if error
         */
        public function insert($table = null, $data)
        {
            if ($table === null || empty($data) || !is_array($data)) {
                exception("mysql", "Invalid array for table: <strong>" . $table . "</strong>.");
            }

            $q = "INSERT INTO `" . $table . "` ";
            $v = '';
            $k = '';

            foreach ($data as $key => $val) {
                $k .= "`$key`, ";

                if (strtolower($val) == 'null') $v .= "NULL, ";
                elseif (strtolower($val) == 'now()') $v .= "NOW(), ";
                else $v .= "'" . $this->escape($val) . "', ";
            }

            $q .= "(" . rtrim($k, ', ') . ") VALUES (" . rtrim($v, ', ') . ");";

            if ($this->query($q)) return $this->insertid();
            else return false;
        }

        /**
         * Database::update()
         * Update query with an array
         * @param mixed $table
         * @param mixed $data
         * @param string $where
         * @return query_id
         */
        public function update($table = null, $data, $where = '1')
        {
            if ($table === null || empty($data) || !is_array($data)) {
                exception("mysql", "Invalid array for table: <b>" . $table . "</b>.");
            }

            $q = "UPDATE `" . $table . "` SET ";

            foreach ($data as $key => $val) {
                if (strtolower($val) == 'null') $q .= "`$key` = NULL, ";
                elseif (strtolower($val) == 'now()')  $q .= "`$key` = NOW(), ";
                elseif (strtolower($val) == 'default()') $q .= "`$key` = DEFAULT($val), ";
                elseif(preg_match("/^inc\((\-?[\d\.]+)\)$/i", $val, $m)) $q.= "`$key` = `$key` + $m[1], ";
                else $q .= "`$key`='" . $this->escape($val) . "', ";
            }

            $q = rtrim($q, ', ') . ' WHERE ' . $where . ';';

            return $this->query($q);
        }

        /**
         * Database::delete()
         * Delete records
         * @param mixed $table
         * @param string $where
         * @return
         */
        public function delete($table, $where = '')
        {
            $q = !$where ? 'DELETE FROM ' . $table : 'DELETE FROM ' . $table . ' WHERE ' . $where;

            return $this->query($q);
        }

    /**
         * Database::insert_id()
         * Returns last inserted ID
         * @param integer $query_id
         * @return
         */
        public function insertid()
        {
            return mysqli_insert_id($this->link_id);
        }
        /**
         * Database::affected()
         * Returns the number of affected rows
         * @param integer $query_id
         * @return
         */
        public function affected()
        {
            return mysqli_affected_rows($this->link_id);
        }

 /**
         * Database::numrows()
         *
         * @param integer $query_id
         * @return
         */
        public function numrows($query_id)
        {
            if ($query_id) $this->query_id = $query_id;

            $this->num_rows = mysqli_num_rows($this->query_id);

            return $this->num_rows;
        }

        /**
         * Database::fetchrow()
         * Fetches one row of data
         * @param integer $query_id
         * @return fetched row
         */
        public function fetchrow($query_id)
        {
            if ($query_id) $this->query_id = $query_id;

            $this->fetch_row = mysqli_fetch_row($this->query_id);

            return $this->fetch_row;
        }

        /**
         * Database::numfields()
         *
         * @param integer $query_id
         * @return
         */
        public function numfields($query_id)
        {
            if ($query_id) $this->query_id = $query_id;

            $this->num_fields = mysqli_num_fields($this->query_id);

            return $this->num_fields;
        }

        /**
         * Database::field_count()
         *
         * @param integer $query_id
         * @return
         */
        public function field_count()
        {
            $this->field_count = mysqli_field_count($this->link_id);

            return $this->field_count;
        }

        /**
         * Database::fetch_fields()
         *
         * @param integer $query_id
         * @return
         */
        public function fetch_fields($query_id)
        {
            if ($query_id) $this->query_id = $query_id;

            $this->fetch_fields = mysqli_fetch_fields($this->query_id);

            return $this->fetch_fields;
        }

        /**
         * Database::show()
         *
         * @return
         */
        public function show()
        {
            return "<br><br><b>Debug Mode - All Queries :</b><hr size=1> " . $this->query_show . "<br>";
        }

        /**
         * Database::pre()
         *
         * @return
         */
        public function pre($arr)
        {
            print '<pre>' . @print_r($arr, true) . '</pre>';
        }


        /**
         * Database::escape()
         * @param mixed $string
         * @return
         */
        public function escape($string)
        {
            if (is_array($string)) {
                foreach ($string as $key => $value) $string[$key] = $this->escape_($value);
            } else $string = $this->escape_($string);

            return $string;
        }

        /**
         * Database::escape_()
         *
         * @param mixed $string
         * @return Database::quote()
         */
        private function escape_($string)
        {
            return mysqli_real_escape_string($this->link_id, $string);
        }

        /**
         * Database::getDB()
     *
         * @return
         */
        public function getDB()
        {
            return $this->database;
        }

        /**
         * Database::getServer()
     *
         * @return
         */
        public function getServer()
        {
            return $this->server;
        }

        /**
         * Database::getLink()
     *
         * @return
         */
        public function getLink()
        {
            return $this->link_id;
        }
    }
