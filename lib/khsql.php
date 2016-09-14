<?php
    namespace Octo;

    class Khsql
    {
        private $lite, $table;

        public function __construct($ns = 'core.cache')
        {
            $this->lite = new \mysqli(
                Config::get('database.host', '127.0.0.1'),
                Config::get('database.username', 'root'),
                Config::get('database.password', 'root'),
                Config::get('database.database', SITE_NAME)
            );

            $this->table = $table = str_replace('.', '_', $ns) . '_my';

            $q      = "CREATE TABLE IF NOT EXISTS $table (k VARCHAR (255) PRIMARY KEY, v LONGTEXT, ts INT (20) UNSIGNED);";
            $res    = $this->raw($q);
        }

        public function escape($string)
        {
            return $this->lite->real_escape_string($string);
        }

        public function raw($q)
        {
            return $this->lite->query($q);
        }

        public function getLite()
        {
            return $this->lite;
        }

        public static function instance($collection)
        {
            $key    = sha1($collection);
            $has    = Instance::has('liveStore', $key);

            if (true === $has) {
                return Instance::get('liveStore', $key);
            } else {
                return Instance::make('liveStore', $key, new self($collection));
            }
        }

        public function set($key, $value)
        {
            $q = "DELETE FROM $this->table WHERE k = '" . $this->escape($key) . "'";
            $this->raw($q);

            $q = "INSERT INTO $this->table (k, v, ts) VALUES ('" . $this->escape($key) . "', '" . $this->escape(serialize($value)) . "', '" . time() . "')";
            $this->raw($q);

            return $this;
        }

        public function get($key, $default = null)
        {
            $q = "SELECT v FROM $this->table WHERE k = '" . $this->escape($key) . "'";
            $res = $this->lite->query($q);

            while ($row = $res->fetch_array()) {
                return unserialize($row['v']);
            }

            return $default;
        }

        public function delete($key)
        {
            $q = "DELETE FROM $this->table WHERE k = '" . $this->escape($key) . "'";
            $this->raw($q);

            return true;
        }

        public function del($key)
        {
            return $this->delete($key);
        }

        public function has($key)
        {
            $q = "SELECT k FROM $this->table WHERE k = '" . $this->escape($key) . "'";
            $res = $this->lite->query($q);

            while ($row = $res->fetch_array()) {
                return true;
            }

            return false;
        }

        public function age($key)
        {
            $q = "SELECT ts FROM $this->table WHERE k = '" . $this->escape($key) . "'";
            $res = $this->lite->query($q);

            while ($row = $res->fetch_array()) {
                return (int) $row['ts'];
            }

            return false;
        }

        public function getAge($key)
        {
            return $age = $this->age($key) ? date('d/m/Y H:i:s', $age) : false;
        }

        public function incr($key, $by = 1)
        {
            $old = $this->get($key, 0);
            $new = $old + $by;

            $this->set($key, $new);

            return $new;
        }

        public function decr($key, $by = 1)
        {
            $old = $this->get($key, 1);
            $new = $old - $by;

            $this->set($key, $new);

            return $new;
        }

        public function hset($hash, $key, $value)
        {
           return $this->set("$hash.$key", $value);
        }

        public function hget($hash, $key, $default = null)
        {
            return $this->get("$hash.$key", $default);
        }

        public function hdelete($hash, $key)
        {
            return $this->delete("$hash.$key");
        }

        public function hdel($hash, $key)
        {
            return $this->hdelete($hash, $key);
        }

        public function hhas($hash, $key)
        {
            return $this->has("$hash.$key");
        }

        public function hage($hash, $key)
        {
            return $this->age("$hash.$key");
        }

        public function getHage($hash, $key)
        {
            return $age = $this->hage($hash, $key) ? date('d/m/Y H:i:s', $age) : false;
        }

        public function hincr($hash, $key, $by = 1)
        {
            $old = $this->hget($hash, $key, 1);
            $new = $old + $by;

            $this->hset($hash, $key, $new);

            return $new;
        }

        public function hdecr($hash, $key, $by = 1)
        {
            $old = $this->hget($hash, $key, 1);
            $new = $old - $by;

            $this->hset($hash, $key, $new);

            return $new;
        }

        public function keys($pattern = '*')
        {
            $coll = [];

            $q = "SELECT k FROM $this->table WHERE k LIKE '" . $this->escape(str_replace('*', '%', $pattern)) . "'";

            $res = $this->lite->query($q);

            while ($row = $res->fetch_array()) {
                $coll[] = $row['k'];
            }

            return \SplFixedArray::fromArray($coll);
        }

        public function hkeys($hash)
        {
            $coll = [];

            $q = "SELECT k, v FROM $this->table WHERE k LIKE '" . $this->escape($hash) . ".%'";

            $res = $this->lite->query($q);

            while ($row = $res->fetch_array()) {
                $coll[] = str_replace($hash . '.', '', $row['k']);
            }

            return \SplFixedArray::fromArray($coll);
        }

        public function hkeysbool($hash)
        {
            $coll = [];

            $q = "SELECT k, v FROM $this->table WHERE k LIKE '" . $this->escape($hash) . ".%'";

            $res = $this->lite->query($q);

            while ($row = $res->fetch_array()) {
                if (is_bool(unserialize($row['v']))) {
                    $coll[] = str_replace($hash . '.', '', $row['k']);
                }
            }

            return \SplFixedArray::fromArray($coll);
        }
    }
