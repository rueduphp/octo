<?php
namespace Octo;

use SQLite3;

class Lite implements \ArrayAccess
{
    /** @var string */
    private $ns;

    /**
     * @param string $ns
     * @param array $data
     * @throws \Exception
     */
    public function __construct(string $ns = 'core', array $data = [])
    {
        $file = storage_path() . '/db/' . $ns . '_cache.db';

        if (!is_dir(storage_path() . '/db')) {
            File::mkdir(storage_path() . '/db');
        }

        if (!is_file($file)) {
            File::create($file);
        }

        $this->ns = $ns;
        $db = new SQLite3($file);

        setCore("cachelite.link.$ns", $db);

        $q = "CREATE TABLE IF NOT EXISTS cachedb (data_key VARCHAR PRIMARY KEY, data_value);";
        $this->db->exec($q);

        if (!empty($data)) {
            foreach ($data as $k => $v) {
                $this->set($k, $v);
            }
        }
    }

    public function __set($k, $v)
    {
        return $this->set($k, $v);
    }

    public function __get($k)
    {
        if ($k === 'db') {
            $key = 'cachelite.link.' . $this->ns;

            return getCore($key);
        }

        return $this->get($k);
    }

    public function __isset($k)
    {
        if ($k === 'db') {
            $key = 'cachelite.link.' . $this->ns;

            return hasCore($key);
        }

        return $this->has($k);
    }

    public function __unset($k)
    {
        if ($k === 'db') {
            $key = 'cachelite.link.' . $this->ns;

            return delCore($key);
        }

        return $this->del($k);
    }

    /**
     * @param string $key
     * @param $value
     * @return Lite
     */
    public function set(string $key, $value): self
    {
        $key = $this->ns . '.' . $key;
        $value = SQLite3::escapeString(serialize($value));

        $this->db->exec(
            "INSERT INTO cachedb (data_key, data_value) VALUES ('{$key}', '{$value}');"
        );

        return $this;
    }

    public function offsetSet($k, $v)
    {
        return $this->set($k, $v);
    }

    public function get($k, $default = null)
    {
        $k      = $this->ns . '.' . $k;
        $query  = "SELECT data_value FROM cachedb WHERE data_key = '$k'";
        $result = $this->db->query($query);

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            return unserialize($row['data_value']);
        }

        return $default;
    }

    public function getOr($k, callable $v)
    {
        if (!$this->has($k)) {
            $v = $v();
        } else {
            $v = $this->get($k);
        }

        return $v;
    }

    public function keys($pattern = '*')
    {
        $collection = [];

        $pattern = $this->ns . '.' . str_replace('*', '%', $pattern);

        $query = "SELECT data_key FROM cachedb WHERE data_key LIKE '" . SQLite3::escapeString($pattern) . "'";

        $result = $this->db->query($query);

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $collection[] = str_replace($this->ns . '.', '', $row['data_key']);
        }

        return $collection;
    }

    public function offsetGet($k)
    {
        return $this->get($k);
    }

    public function has($k)
    {
        $k      = $this->ns . '.' . $k;
        $query  = "SELECT COUNT(data_key) AS nb FROM cachedb WHERE data_key = '$k'";
        $result = $this->db->query($query);

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            return $row['nb'] > 0;
        }

        return false;
    }

    public function offsetExists($k)
    {
        return $this->has($k);
    }

    public function offsetUnset($k)
    {
        return $this->delete($k);
    }

    public function delete($k)
    {
        $k = $this->ns . '.' . $k;
        $query = "DELETE FROM cachedb WHERE data_key = '$k'";
        $this->db->exec($query);

        return $this;
    }

    public function forget($k)
    {
        return $this->delete($k);
    }

    public function remove($k)
    {
        return $this->delete($k);
    }

    public function del($k)
    {
        return $this->delete($k);
    }

    public function incr($k, $by = 1)
    {
        $old = $this->get($k, 0);
        $new = $old + $by;

        $this->set($k, $new);

        return $new;
    }

    public function decr($k, $by = 1)
    {
        $old = $this->get($k, 1);
        $new = $old - $by;

        $this->set($k, $new);

        return $new;
    }

    public function fill(array $data)
    {
        foreach ($data as $k => $v) {
            $this->set($k, $v);
        }

        return $this;
    }

    public function __call($m, $a)
    {
        if (fnmatch('get*', $m)) {
            $key = Inflector::uncamelize(substr($m, 3));
            $default = empty($a) ? null : current($a);

            return $this->get($key, $default);
        } elseif (fnmatch('set*', $m)) {
            $key = Inflector::uncamelize(substr($m, 3));

            return $this->set($key, current($a));

        } elseif (fnmatch('has*', $m)) {
            $key = Inflector::uncamelize(substr($m, 3));

            return $this->has($key);

        } elseif (fnmatch('del*', $m)) {
            $key = Inflector::uncamelize(substr($m, 3));

            return $this->delete($key);
        } else {
            $closure = $this->get($m);

            if (is_string($closure) && fnmatch('*::*', $closure)) {
                list($c, $f) = explode('::', $closure, 2);

                try {
                    $i = lib('caller')->make($c);

                    return call_user_func_array([$i, $f], $a);
                } catch (\Exception $e) {
                    $default = empty($a) ? null : current($a);

                    return empty($closure) ? $default : $closure;
                }
            } else {
                if (is_callable($closure)) {
                    return call_user_func_array($closure, $a);
                }

                if (!empty($a) && empty($closure)) {
                    if (count($a) == 1) {
                        return $this->set($m, current($a));
                    }
                }

                $default = empty($a) ? null : current($a);

                return empty($closure) ? $default : $closure;
            }
        }
    }
}
