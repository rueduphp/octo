<?php
    namespace Octo;

    class Cachelite
    {
        use Notifiable;

        private $dir;
        private $id;
        private $db;
        private static $instances = [];

        private function getPath($k)
        {
            return $this->dir . '.' . $k;
        }

        public function pull($key, $default = null)
        {
            $value = $this->get($key, $default);

            $this->forget($key);

            return $value;
        }

        public function __construct($ns = 'core')
        {
            $this->dir = $ns;

            $file = path('storage') . DS . Strings::urlize($ns, '');

            $new = !file_exists($file);

            $this->db = new \PDO('sqlite:' . $file);
            $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);

            if ($new) {
                File::copy(__DIR__ . DS . 'db', $file);
            }

            $this->id = sha1('lite' . $ns);
        }

        public static function instance($ns = 'core', $dir = null)
        {
            $key = sha1(serialize(func_get_args()));

            $instance = isAke(static::$instances, $key, null);

            if (!$instance) {
                $instance = new static($ns, $dir);

                static::$instances[$key] = $instance;
            }

            return $instance;
        }

        public function __call($m, $a)
        {
            if ('if' == $m) {
                return call_user_func_array([$this, 'cacheIf'], $a);
            }
        }

        public function cacheIf($k, $condition, $value, $expire = null)
        {
            $condition  = value($condition);
            $value      = value($value);

            if ($condition) {
                $this->set($k, $value, $expire);
            }

            return $value;
        }

        public function setDirectory($dir)
        {
            $this->dir = $dir;

            return $this;
        }

        public function getDirectory()
        {
            return $this->dir;
        }

        public function set($k, $v, $expire = null)
        {
            $file = $this->getPath($k);

            $this->_delete($file);

            $v = value($v);

            $this->_put($file, serialize($v), is_null($expire) ? 0 : time() + $expire);

            return $this;
        }

        public function put($k, $v, $expire = null)
        {
            return $this->set($k, $v, $expire);
        }

        public function setMany(array $values, $e = null)
        {
            foreach ($values as $k => $v) {
                $this->set($k, $v, $e);
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

        public function setExpireAt($k, $v, $timestamp)
        {
            $file = $this->getPath($k);

            $this->_delete($file);

            $this->_put($file, serialize($v), $timestamp);

            return $this;
        }

        public function setExp($k, $v, $expire)
        {
            return $this->set($k, $v, $expire);
        }

        public function setExpire($k, $v, $expire)
        {
            return $this->set($k, $v, $expire);
        }

        public function expire($k, $expire)
        {
            $v = $this->get($k);

            return $this->set($k, $v, $expire);
        }

        public function expireAt($k, $timestamp)
        {
            $v = $this->get($k);

            return $this->set($k, $v, $timestamp);
        }

        public function get($k, $d = null)
        {
            $file = $this->getPath($k);

            if ($this->_exists($file)) {
                $row = $this->_read($file);

                if ($row) {
                    $age = $row['e'];

                    if (0 == $age || $age >= time()) {
                        return value(unserialize($row['v']));
                    } else {
                        $this->_delete($file);
                    }
                }
            }

            return value($d);
        }

        public function forever($k, $v)
        {
            return $this->set($k, $v);
        }

        public function getOr($k, callable $c, $e = null)
        {
            $res = $this->get($k, 'octodummy');

            if ('octodummy' == $res) {
                $this->set($k, $res = $c(), $e);
            }

            return $res;
        }

        public function remember($k, $c, $e = null)
        {
            if (!is_callable($c)) {
                $c = function () use ($c) {return $c;};
            }

            return $this->getOr($k, $c, $e);
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

        public function session($k, $v = 'dummyget', $e = null)
        {
            $user       = session('web')->getUser();
            $isLogged   = !is_null($user);
            $key        = $isLogged ? sha1(lng() . '.' . forever() . '1.' . $k) :  sha1(lng() . '.' . forever() . '0.' . $k);

            return 'dummyget' == $v ? $this->get($key) : $this->set($key, $v, $e);
        }

        public function my($k, $v = 'dummyget', $e = null)
        {
            $user       = my('web')->getUser();
            $isLogged   = !is_null($user);
            $key        = $isLogged ? sha1(lng() . '.' . forever() . '1.' . $k) :  sha1(lng() . '.' . forever() . '0.' . $k);

            return 'dummyget' == $v ? $this->get($key) : $this->set($key, $v, $e);
        }

        public function aged($k, callable $c, $a)
        {
            $k = sha1($this->dir) . '.' . $k;

            return $this->until($k, $c, $a);
        }

        public function has($k)
        {
            $file = $this->getPath($k);

            if ($this->_exists($file)) {
                $row = $this->_read($file);

                if (!$row) {
                    return false;
                }

                $age = $row['e'];

                if (0 == $age || $age >= time()) {
                    return true;
                } else {
                    $this->_delete($file);
                }
            }

            return false;
        }

        public function age($k)
        {
            $file = $this->getPath($k);

            if ($this->_exists($file)) {
                $row = $this->_read($file);
                $age = $row['e'];

                if (!$row) {
                    return null;
                }

                if (0 == $age || $age >= time()) {
                    return $age;
                } else {
                    $this->_delete($file);
                }
            }

            return null;
        }

        public function delete($k)
        {
            $file = $this->getPath($k);

            if ($this->_exists($file)) {
                $this->_delete($file);

                return true;
            }

            return false;
        }

        public function del($k)
        {
            return $this->delete($k);
        }

        public function remove($k)
        {
            return $this->delete($k);
        }

        public function forget($k)
        {
            return $this->delete($k);
        }

        public function destroy($k)
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

        public function increment($k, $by = 1)
        {
            return $this->incr($k, $by);
        }

        public function decr($k, $by = 1)
        {
            $old = $this->get($k, 0);
            $new = $old - $by;

            $this->set($k, $new);

            return $new;
        }

        public function decrement($k, $by = 1)
        {
            return $this->decr($k, $by);
        }

        private function cleanCache()
        {
            $q = "DELETE FROM d WHERE e > 0 AND e < " . time();
            $this->q($q);
        }

        public function keys($pattern = '*')
        {
            $this->cleanCache();
            $pattern    = str_replace('*', '%', $pattern);
            $q          = "SELECT k FROM d WHERE k LIKE '$pattern'";
            $res        = $this->q($q)->fetchAll();

            if (is_array($res)) {
                $count = count($res);
            } else {
                $count = $res->rowCount();
            }

            $collection = [];

            if (0 < $count) {
                foreach ($res as $row) {
                    array_push($collection, str_replace($this->dir . '.', '', $row['k']));
                }
            }

            return $collection;
        }

        public function flush($pattern = '*')
        {
            $keys = $this->keys($pattern);

            $affected = 0;

            foreach ($keys as $key) {
                $this->_delete($this->getPath($key));
                $affected++;
            }

            return $affected;
        }

        public function clean($pattern = '*')
        {
            $keys = $this->keys($pattern);

            $affected = 0;

            foreach ($keys as $key) {
                $row = $this->_read($this->getPath($key));

                if ($row) {
                    $age = $row['e'];

                    if (0 < (int) $age && (int) $age < time()) {
                        $this->_delete($this->getPath($key));

                        $affected++;
                    }
                }
            }

            return $affected;
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

        public function length($key)
        {
            return strlen($this->get($key));
        }

        public function hset($hash, $key, $value)
        {
            $key = "hash.$hash.$key";

            return $this->set($key, $value);
        }

        public function hsetnx($hash, $key, $value)
        {
            if (!$this->hexists($hash, $key)) {
                $this->hset($hash, $key, $value);

                return true;
            }

            return false;
        }

        public function hget($hash, $key, $default = null)
        {
            $key = "hash.$hash.$key";

            return $this->get($key, $default);
        }

        public function hstrlen($hash, $key)
        {
            if ($value = $this->hget($hash, $key)) {
                return strlen($value);
            }

            return 0;
        }

        public function hgetOr($hash, $k, callable $c)
        {
            if ($this->hexists($hash, $k)) {
                return $this->hget($hash, $k);
            }

            $res = $c();

            $this->hset($hash, $k, $res);

            return $res;
        }

        public function hwatch($hash, $k, callable $exists = null, callable $notExists = null)
        {
            if ($this->hexists($hash, $k)) {
                if (is_callable($exists)) {
                    return $exists($this->hget($hash, $k));
                }
            } else {
                if (is_callable($notExists)) {
                    return $notExists();
                }
            }

            return false;
        }

        public function hReadAndDelete($hash, $key, $default = null)
        {
            if ($this->hexists($hash, $key)) {
                $value = $this->hget($hash, $key);

                $this->hdelete($hash, $key);

                return $value;
            }

            return $default;
        }

        public function hdelete($hash, $key)
        {
            $key = "hash.$hash.$key";

            return $this->delete($key);
        }

        public function hdel($hash, $key)
        {
            return $this->hdelete($hash, $key);
        }

        public function hhas($hash, $key)
        {
            $key = "hash.$hash.$key";

            return $this->has($key);
        }

        public function hexists($hash, $key)
        {
            return $this->hhas($hash, $key);
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

        public function hgetall($hash)
        {
            $this->keys('hash.' . $hash . '.*');

            foreach ($keys as $row) {
                $key = str_replace([$this->dir . '.', "hash.$hash."], '', Arrays::last(explode(DS, $row)));

                yield $key;
                yield unserialize($this->_read($this->getPath($row))['v']);
            }
        }

        public function hvals($hash)
        {
            $this->keys('hash.' . $hash . '.*');

            foreach ($keys as $row) {
                yield unserialize($this->_read($this->getPath($row))['v']);
            }
        }

        public function hlen($hash)
        {
            $keys = $this->keys('hash.' . $hash . '.*');

            return count($keys);
        }

        public function hremove($hash)
        {
            $keys = $this->keys('hash.' . $hash . '.*');

            foreach ($keys as $row) {
                $this->_delete($this->getPath($row));
            }

            return true;
        }

        public function hkeys($hash)
        {
            $keys = $this->keys('hash.' . $hash . '.*');

            foreach ($keys as $row) {
                $key = str_replace([$this->dir . '.', "hash.$hash."], '', Arrays::last(explode(DS, $row)));

                yield $key;
            }
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

        public function until($k, callable $c, $maxAge = null, $args = [])
        {
            $keyAge = $k . '.maxage';
            $v      = $this->get($k);

            if ($v) {
                if (is_null($maxAge)) {
                    return $v;
                }

                $age = $this->get($keyAge);

                if (!$age) {
                    $age = $maxAge - 1;
                }

                if ($age >= $maxAge) {
                    return $v;
                } else {
                    $this->delete($k);
                    $this->delete($keyAge);
                }
            }

            $data = call_user_func_array($c, $args);

            $this->set($k, $data);

            if (!is_null($maxAge)) {
                if ($maxAge < 1000000000) {
                    $maxAge = ($maxAge * 60) + microtime(true);
                }

                $this->set($keyAge, $maxAge);
            }

            return $data;
        }

        public function flash($key, $val = 'octodummy')
        {
            $key = "flash_{$key}";

            if ($val != 'octodummy') {
                $this->set($key, $val);
            } else {
                $val = $this->get($key);
                $this->delete($key);
            }

            return $val != 'octodummy' ? $this : $val;
        }

        public function add($k, $v, $e)
        {
            if (!$this->has($key)) {
                return $this->set($k, $v, $e);
            }

            return $this;
        }

        public function setNow($k, $v, $expire = null)
        {
            $file = $this->getPath($k);

            $this->_delete($file);

            $v = File::value($v);

            $this->_put($file, serialize($v), is_null($expire) ? time() : time() + $expire);

            return $this;
        }

        public function hasNow($k)
        {
            return $this->_exists($this->getPath($k));
        }

        public function getNow($k, $d = null)
        {
            $file = $this->getPath($k);

            if ($this->_exists($file)) {
                return unserialize($this->_read($file)['v']);
            }

            return $d;
        }

        public function delNow($k)
        {
            $file = $this->getPath($k);

            if ($this->_exists($file)) {
                $this->_delete($file);

                return true;
            }

            return false;
        }

        public function ageNow($k)
        {
            $file = $this->getPath($k);

            if ($this->_exists($file)) {
                $row = $this->_read($file);

                if ($row) {
                    return $row['e'];
                }
            }

            return time();
        }

        public function getDel($k, $d = null)
        {
            $value = $this->get($k, $d);

            $this->delete($k);

            return $value;
        }

        public function start($k, $d = null)
        {
            if (!$this->has($k)) {
                Registry::set('cache.buffer.' . $this->id, $k);
                ob_start();

                return $d;
            }

            Registry::delete('cache.buffer.' . $this->id);

            return $this->get($k);
        }

        public function end()
        {
            if ($k = Registry::get('cache.buffer.' . $this->id)) {
                $value = ob_get_clean();

                $this->set($k, $value, $this->getTtl());

                return $value;
            }

            return false;
        }

        public function ttl($e = null)
        {
            if ($e) {
                Registry::set('cache.ttl.' . $this->id, $e);

                return $this;
            }

            return Registry::get('cache.ttl.' . $this->id, $e);
        }

        public function getTtl($e = null)
        {
            return $e ? $e : Registry::get('cache.ttl.' . $this->id, $e);
        }

        private function _delete($k)
        {
            return $this->q("DELETE FROM d WHERE k = " . $this->quote($k));
        }

        private function _put($k, $v, $e)
        {
            $q = "INSERT INTO d (k, v, e)
                VALUES (
                    " . $this->quote($k) . ",
                    " . $this->quote($v) . ",
                    " . $this->quote($e) . "
                );";

            $res = $this->q($q);
        }

        private function _read($k)
        {
            $q          = "SELECT v,e FROM d WHERE k = " . $this->quote($k);
            $res        = $this->q($q)->fetch();

            if (is_array($res)) {
                $count = count($res);
            } else {
                $count = $res->rowCount();
            }

            if (0 < $count) {
                return ['v' => $res['v'], 'e' => (int) $res['e']];
            }

            return false;
        }

        private function _exists($k)
        {
            $q          = "SELECT k FROM d WHERE k = " . $this->quote($k);
            $res        = $this->q($q)->fetch();

            if (is_array($res)) {
                $count = count($res);
            } else {
                if (false === $res) {
                    return false;
                }

                $count = $res->rowCount();
            }

            return $count == 2;
        }

        private function q($query)
        {
            $res = $this->db->prepare($query);

            if (is_object($res)) {
                $res->execute();
            }

            return $res;
        }

        private function quote($value, $parameterType = \PDO::PARAM_STR)
        {
            if (null === $value) {
                return "NULL";
            }

            if (is_string($value)) {
                return $this->db->quote($value, $parameterType);
            }

            return $value;
        }
    }
