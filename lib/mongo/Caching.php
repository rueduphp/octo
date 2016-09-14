<?php
    namespace Octo\Mongo;

    use MongoClient as MGC;
    use Octo\Config as Conf;
    use Octo\Instance;
    use Octo\Exception;

    class Caching
    {
        private $ns, $cnx, $collection;

        public function __construct($ns)
        {
            $this->ns           = $ns;
            $this->collection   = "core.cache";

            $host               = Conf::get('mongo.host', '127.0.0.1');
            $port               = Conf::get('mongo.port', 27017);
            $protocol           = Conf::get('mongo.protocol', 'mongodb');
            $auth               = Conf::get('mongo.auth', true);

            if (true === $auth) {
                $user           = Conf::get('mongo.username', SITE_NAME . '_master');
                $password       = Conf::get('mongo.password');

                $this->connect($protocol, $user, $password, $host, $port);
            } else {
                $this->cnx      = new MGC($protocol . '://' . $host . ':' . $port, ['connect' => true]);
            }

            $this->clean();
        }

        private function clean()
        {
            $coll   = $this->getCollection();

            $coll->remove(['$and' => [['expire' => ['$gt' => 0]], ['expire' => ['$lt' => time()]]]]);
        }

        public static function instance($ns)
        {
            $key    = sha1($ns);
            $has    = Instance::has('DbredisCaching', $key);

            if (true === $has) {
                return Instance::get('DbredisCaching', $key);
            } else {
                return Instance::make('DbredisCaching', $key, new self($ns));
            }
        }

        public static function instanceDb($ns)
        {
            $key    = sha1($ns);
            $has    = Instance::has('DbredisCachingDb', $key);

            if (true === $has) {
                return Instance::get('DbredisCachingDb', $key);
            } else {
                $i = new self($ns);
                list($db, $table) = explode('.', $ns, 2);
                $i = $i->changeCollection("$db.caching")->changeNs($table);

                return Instance::make('DbredisCachingDb', $key, $i);
            }
        }

        public function changeNs($ns)
        {
            $this->ns = $ns;

            return $this;
        }

        public function changeCollection($collection)
        {
            $this->collection = $collection;

            return $this;
        }

        public function setExpire($key, $value, $ttl)
        {
            return $this->set($key, $value, $ttl);
        }

        public function set($key, $value, $ttl = 0)
        {
            $ttl    = 0 < $ttl ? $ttl + time() : $ttl;

            $coll   = $this->getCollection();

            $key    = $this->ns . '.' . $key;

            $exists = $this->_exists($key);

            if (false !== $exists) {
                $update = $coll->update(['key' => $key], ['$set' => ['value' => $value, 'expire' => $ttl]]);
            } else {
                $new    = $coll->insert(['key' => $key, 'value' => $value, 'expire' => $ttl]);
            }

            return $this;
        }

        public function get($key, $default = null)
        {
            $key    = $this->ns . '.' . $key;
            $value  = $this->_exists($key);

            if (false === $value) {
                return $default;
            } elseif (is_null($value)) {
                return $default;
            } else {
                return $value;
            }
        }

        public function expireat($key, $time)
        {
            $coll   = $this->getCollection();

            $key    = $this->ns . '.' . $key;

            $exists = $this->_exists($key);

            if (false !== $exists) {
                $update = $coll->update(['key' => $key], ['$set' => ['expire' => $time]]);
            }

            return $this;
        }

        public function expire($key, $ttl = 0)
        {
            $ttl    = 0 < $ttl ? $ttl + time() : $ttl;

            $coll   = $this->getCollection();

            $key    = $this->ns . '.' . $key;

            $exists = $this->_exists($key);

            if (false !== $exists) {
                $update = $coll->update(['key' => $key], ['$set' => ['expire' => $ttl]]);
            }

            return $this;
        }

        public function exists($key)
        {
            $key = $this->ns . '.' . $key;

            return $this->_exists($key) !== false;
        }

        public function has($key)
        {
            return $this->exists($key);
        }

        public function del($key)
        {
            return $this->delete($key);
        }

        public function delete($key)
        {
            $key = $this->ns . '.' . $key;

            return $this->remove($key);
        }

        public function keys($pattern = '*', $what = null)
        {
            $what       = is_null($what) ? $this->ns : $this->ns . '.' . $what;
            $keys       = [];
            $coll       = $this->getCollection();
            $pattern    = $what . '.' . str_replace('*', '.*', $pattern);
            $query      = ['key' => new \MongoRegex('/^' . $pattern . '/imxsu')];

            $coll->ensureIndex(['key' => 1]);

            $results = new Cursor($coll->find($query));

            foreach ($results as $row) {
                $key = str_replace($what . '.', '', isAke($row, 'key', null));

                if (!is_null($key)) {
                    array_push($keys, $key);
                }
            }

            return $keys;
        }

        public function incr($key, $by = 1)
        {
            $val = $this->get($key);

            if (!strlen($val)) {
                $val = 1;
            } else {
                $val = (int) $val;
                $val += $by;
            }

            $this->set($key, $val);

            return $val;
        }

        public function decr($key, $by = 1)
        {
            $val = $this->get($key);

            if (!strlen($val)) {
                $val = 0;
            } else {
                $val = (int) $val;
                $val -= $by;
                $val = 0 > $val ? 0 : $val;
            }

            $this->set($key, $val);

            return $val;
        }

        public function ttl($key)
        {
            $coll   = $this->getCollection();
            $coll->ensureIndex(['key' => 1]);
            $row    = $coll->findOne(['key' => $key]);

            if ($row) {
                return isAke($row, 'expire', 0);
            }

            return 0;
        }

        private function _exists($key)
        {
            $coll   = $this->getCollection();
            $coll->ensureIndex(['key' => 1]);
            $row    = $coll->findOne(['key' => $key]);

            if ($row) {
                $expire = isAke($row, 'expire', 0);

                if (0 < $expire) {
                    if ($expire < time()) {
                        $this->remove($key);

                        return false;
                    }
                }

                return isAke($row, 'value', null);
            }

            return false;
        }

        private function remove($key)
        {
            $coll   = $this->getCollection();
            $coll->ensureIndex(['key' => 1]);

            $delete = $coll->remove(['key' => $key], ["justOne" => true]);

            return $this;
        }

        public function hset($hash, $key, $value, $ttl = 0)
        {
            return $this->set("$hash.$key", $value, $ttl);
        }

        public function httl($hash, $key)
        {
            return $this->ttl("$hash.$key");
        }

        public function hget($hash, $key, $default = null)
        {
            return $this->get("$hash.$key", $default);
        }

        public function hexpire($hash, $key, $ttl = 0)
        {
            return $this->expire("$hash.$key", $ttl);
        }

        public function hexpireat($hash, $key, $time)
        {
            return $this->expireat("$hash.$key", $time);
        }

        public function hdelete($hash, $key)
        {
            return $this->del("$hash.$key");
        }

        public function hdel($hash, $key)
        {
            return $this->del("$hash.$key");
        }

        public function hincr($hash, $key, $by = 1)
        {
            return $this->incr("$hash.$key", $by);
        }

        public function hdecr($hash, $key, $by = 1)
        {
            return $this->decr("$hash.$key", $by);
        }

        public function hkeys($hash, $pattern = '*')
        {
            return $this->keys($pattern, $hash);
        }

        public function hhas($hash, $key)
        {
            return $this->has("$hash.$key");
        }

        public function hexists($hash, $key)
        {
            return $this->has("$hash.$key");
        }

        public function hgetall($hash)
        {
            $collection = [];
            $keys       = $this->hkeys($hash);

            foreach ($keys as $key) {
                $value = $this->hget($hash, $key, null);
                $collection[$key] = $value;
            }

            return $collection;
        }

        private function connect($protocol, $user, $password, $host, $port, $incr = 0)
        {
            try {
                $this->cnx = new MGC($protocol . '://' . $user . ':' . $password . '@' . $host . ':' . $port, ['connect' => true]);
            } catch (\MongoConnectionException $e) {
                if (APPLICATION_ENV == 'production') {
                    $incr++;

                    if (20 < $incr) {
                        $this->connect($protocol, $user, $password, $host, $port, $incr);
                    } else {
                        dd($e->getMessage());
                    }
                } else {
                    $this->connect($protocol, $user, $password, $host, $port, $incr);
                }
            }
        }

        private function getCollection($collection = null)
        {
            $collection = is_null($collection) ? $this->collection : $collection;

            $odm        = $this->cnx->selectDB(SITE_NAME);

            return $odm->selectCollection($collection);
        }
    }
