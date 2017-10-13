<?php
    namespace Octo;

    use SQLite3;

    class Memolite
    {
        private $ns;

        public function __construct($ns = 'core')
        {
            $this->ns = $ns;

            $link = Now::get("memolite.link", null);

            if (is_null($link)) {
                $link = new SQLite3(':memory:');
                $q = "CREATE TABLE IF NOT EXISTS infosdb (data_key VARCHAR PRIMARY KEY, data_value);";
                $res = self::$connexion->exec($q);

                Now::set("memolite.link", $link);
            }
        }

        public function __get($key)
        {
            if ($key == 'db') {
                return Now::get("memolite.link", null);
            }
        }

        public function set($k, $v)
        {
            $k      = $this->ns . '.' . $k;
            $query  = "DELETE FROM infosdb WHERE data_key = '$k'";
            $this->db->exec($query);

            $query = "INSERT INTO infosdb (data_key, data_value) VALUES('$k', '" . SQLite3::escapeString(serialize($v)) . "');";

            $this->db->exec($query);

            return $this;
        }

        public function get($k, $default = null)
        {
            $k      = $this->ns . '.' . $k;
            $query  = "SELECT data_value FROM infosdb WHERE data_key = '$k'";
            $result = $this->db->query($query);

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                return unserialize($row['data_value']);
            }

            return $default;
        }

        public function keys($pattern = '*')
        {
            $collection = [];

            $pattern = $this->ns . '.' . str_replace('*', '%', $pattern);

            $query = "SELECT data_key FROM infosdb WHERE data_key LIKE '" . SQLite3::escapeString($pattern) . "'";

            $result = $this->db->query($query);

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $collection[] = str_replace($this->ns . '.', '', $row['data_key']);
            }

            return $collection;
        }

        public function delete($k)
        {
            return $this->del($k);
        }

        public function forget($k)
        {
            return $this->del($k);
        }

        public function destroy($k)
        {
            return $this->del($k);
        }

        public function del($k)
        {
            $k = $this->ns . '.' . $k;
            $query = "DELETE FROM infosdb WHERE data_key = '$k'";
            $this->db->exec($query);

            return $this;
        }

        public function has($k)
        {
            $k = $this->ns . '.' . $k;
            $query = "SELECT COUNT(data_key) AS nb FROM infosdb WHERE data_key = '$k'";
            $result = $this->db->query($query);

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                return $row['nb'] > 0;
            }

            return false;
        }

        public function getOr($k, callable $c)
        {
            if ($this->has($k)) {
                return $this->get($k);
            }

            $res = $c();

            return $this->set($k, $res);
        }

        public function incr($name, $by = 1)
        {
            $old = $this->get($name, 0);
            $new = $old + $by;

            $this->set($name, $new);

            return (int) $new;
        }

        public function decr($name, $by = 1)
        {
            $old = $this->get($name, 1);
            $new = $old - $by;

            $this->set($name, $new);

            return (int) $new;
        }

        public function increment($name, $by = 1)
        {
            $old = $this->get($name, 0);
            $new = $old + $by;

            $this->set($name, $new);

            return (int) $new;
        }

        public function decrement($name, $by = 1)
        {
            $old = $this->get($name, 1);
            $new = $old - $by;

            $this->set($name, $new);

            return (int) $new;
        }

        public function hset($h, $k, $v)
        {
            return $this->set("hash.$h.$k", $v);
        }

        public function hget($h, $k, $d)
        {
            return $this->get("hash.$h.$k", $d);
        }

        public function hgetOr($h, $k, callable $c)
        {
            return $this->getOr("hash.$h.$k", $c);
        }

        public function hhas($h, $k, $d)
        {
            return $this->has("hash.$h.$k");
        }

        public function hdel($h, $k, $d)
        {
            return $this->delete("hash.$h.$k");
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

        public function getDel($k, $d = null)
        {
            $value = $this->get($k, $d);

            $this->del($k);

            return $value;
        }

        public function pull($k, $d = null)
        {
            return $this->getDel($k, $d);
        }

        public function cacheIf($k, $condition, $value)
        {
            $condition  = value($condition);
            $value      = value($value);

            if ($condition) {
                $this->set($k, $value);
            }

            return $value;
        }

        public function put($k, $v)
        {
            return $this->set($k, $v);
        }

        public function setMany(array $values)
        {
            foreach ($values as $k => $v) {
                $this->set($k, $v);
            }

            return $this;
        }

        public function many(array $keys)
        {
            $return = [];

            foreach ($keys as $key) {
                $return[$key] = $this->get($key);
            }

            return $return;
        }

        public function setnx($key, $value, $expire = null)
        {
            if (!$this->has($key)) {
                $this->set($key, $value, $expire);

                return true;
            }

            return false;
        }

        public function replace($key, $value, $expire = null)
        {
            if ($this->has($key)) {
                $this->set($key, $value, $expire);

                return true;
            }

            return false;
        }

        public function watch($k, callable $exists = null, callable $notExists = null)
        {
            if ($this->has($k)) {
                if (is_callable($exists)) {
                    return $exists($this->get($k));
                }
            } else {
                if (is_callable($notExists)) {
                    return $notExists();
                }
            }

            return false;
        }

        public function readAndDelete($key, $default = null)
        {
            if ($this->has($key)) {
                $value = $this->get($key);

                $this->delete($key);

                return $value;
            }

            return $default;
        }

        public function rename($keyFrom, $keyTo, $default = null)
        {
            $value = $this->readAndDelete($keyFrom, $default);

            return $this->set($keyTo, $value);
        }

        public function copy($keyFrom, $keyTo)
        {
            return $this->set($keyTo, $this->get($keyFrom));
        }

        public function getSize($key)
        {
            return strlen($this->get($key));
        }

        public function sadd($key, $value)
        {
            $tab = $this->get($key, []);
            $tab[] = $value;

            return $this->set($key, $tab);
        }

        public function scard($key)
        {
            $tab = $this->get($key, []);

            return count($tab);
        }

        public function sinter()
        {
            $tab = [];

            foreach (func_get_args() as $key) {
                $tab = array_intersect($tab, $this->get($key, []));
            }

            return $tab;
        }

        public function sunion()
        {
            $tab = [];

            foreach (func_get_args() as $key) {
                $tab = array_merge($tab, $this->get($key, []));
            }

            return $tab;
        }

        public function sinterstore()
        {
            $args = func_get_args();

            $destination = array_shift($args);

            $tab = [];

            foreach ($args as $key) {
                $tab = array_intersect($tab, $this->get($key, []));
            }

            return $this->set($destination, $tab);
        }

        public function sunionstore()
        {
            $args = func_get_args();

            $destination = array_shift($args);

            $tab = [];

            foreach ($args as $key) {
                $tab = array_merge($tab, $this->get($key, []));
            }

            return $this->set($destination, $tab);
        }

        public function sismember($hash, $key)
        {
            return in_array($key, $this->get($hash, []));
        }

        public function smembers($key)
        {
            return $this->get($key, []);
        }

        public function srem($hash, $key)
        {
            $tab = $this->get($hash, []);

            $new = [];

            $exists = false;

            foreach ($tab as $row) {
                if ($row != $key) {
                    $new[] = $row;
                } else {
                    $exists = true;
                }
            }

            if ($exists) {
                $this->set($hash, $new);

                return true;
            }

            return false;
        }

        public function smove($from, $to, $key)
        {
            if ($this->sismember($from, $key)) {
                $this->srem($from, $key);

                if (!$this->sismember($to, $key)) {
                    $this->sadd($to, $key);
                }

                return true;
            }

            return false;
        }
    }
