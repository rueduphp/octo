<?php
namespace Octo;

use Illuminate\Database\Schema\Blueprint;

class BreezeModel extends Elegantmemory
{
    protected $table        = 'breeze';
    public $timestamps      = false;
    public $incrementing    = false;
    protected $primaryKey   = 'k';
}

class Breeze implements FastCacheInterface
{
    private $dir;
    private $id;
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

    /**
     * Breeze constructor.
     * @param string $ns
     * @throws \ReflectionException
     */
    public function __construct(string $ns = 'core')
    {
        $this->dir  = $ns;
        $this->id   = sha1('caching' . $ns);

        $this->configure()->cleanCache();
    }

    /**
     * @return $this
     * @throws \ReflectionException
     */
    protected function configure()
    {
        if (!has('breeze.config')) {
            $schema = BreezeModel::schema();

            $schema->create('breeze', function (Blueprint $table) {
                $table->string('k')->primary()->unique();
                $table->longText('v')->nullable();
                $table->unsignedBigInteger('e')->index();
            });

            set('breeze.config', true);
        }

        return $this;
    }

    /**
     * @param string $ns
     * @return mixed|static
     * @throws \ReflectionException
     */
    public static function instance($ns = 'core')
    {
        $key = sha1(serialize(func_get_args()));

        $instance = isAke(static::$instances, $key, null);

        if (!$instance) {
            $instance = new static($ns);

            static::$instances[$key] = $instance;
        }

        return $instance;
    }

    public function __call($m, $a)
    {
        if ('if' === $m) {
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

        $this->_put($file, serialize(value($v)), $timestamp);

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

                if (0 === $age || $age >= time()) {
                    return value(unserialize($row['v']));
                } else {
                    $this->_delete($file);
                }
            }
        }

        return value($d);
    }

    /**
     * @param string $k
     * @param callable $c
     *
     * @return mixed
     *
     * @throws \ReflectionException
     */
    public function forever(string $k, callable $c)
    {
        return $this->getOr($k, $c);
    }

    /**
     * @param string $k
     * @param callable $c
     * @param null $e
     *
     * @return mixed|null
     *
     * @throws \ReflectionException
     */
    public function getOr(string $k, callable $c, $e = null)
    {
        $res = $this->get($k, 'octodummy');

        if ('octodummy' === $res) {
            $this->set($k, $res = callCallable($c), $e);
        }

        return $res;
    }

    /**
     * @param string $k
     * @param $c
     * @param null $e
     *
     * @return mixed|null
     *
     * @throws \ReflectionException
     */
    public function remember(string $k, $c, $e = null)
    {
        if (!is_callable($c)) {
            $c = function () use ($c) {return $c;};
        }

        return $this->getOr($k, $c, $e);
    }

    /**
     * @param string $k
     * @param callable|null $exists
     * @param callable|null $notExists
     *
     * @return bool|mixed|null
     *
     * @throws \ReflectionException
     */
    public function watch(string $k, ?callable $exists = null, ?callable $notExists = null)
    {
        if ($this->has($k)) {
            if (is_callable($exists)) {
                return callCallable($exists, $this->get($k));
            }
        } else {
            if (is_callable($notExists)) {
                return callCallable($notExists);
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

    /**
     * @return mixed
     */
    public function cleanCache()
    {
        return BreezeModel::where('e', '>', 0)->where('e', '<', time())->delete();
    }

    /**
     * @return array
     */
    public function all(): array
    {
        $collection = [];

        foreach($this->keys() as $key) {
            $collection[$key] = $this->get($key);
        }

        return $collection;
    }

    /**
     * @param string $pattern
     *
     * @return array
     */
    public function keys(string $pattern = '*')
    {
        $this->cleanCache();

        $pattern    = str_replace('*', '%', $pattern);

        $rows = BreezeModel::select('k')
            ->where('k', 'like', $this->dir . '.' . $pattern)
            ->get()
        ;

        $collection = [];

        foreach ($rows as $row) {
            array_push($collection, str_replace($this->dir . '.', '', $row->k));
        }

        return $collection;
    }

    public function flush($pattern = '*')
    {
        $keys = $this->keys($pattern);

        $affected = 0;

        foreach ($keys as $key) {
            $this->_delete($this->getPath($key));
            ++$affected;
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

                if (0 < $age && $age < time()) {
                    $this->_delete($this->getPath($key));

                    ++$affected;
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
        $keys = $this->keys('hash.' . $hash . '.*');

        foreach ($keys as $row) {
            $key = str_replace([$this->dir . '.', "hash.$hash."], '', Arrays::last(explode(DS, $row)));

            yield $key;
            yield unserialize($this->_read($this->getPath($row))['v']);
        }
    }

    public function hvals($hash)
    {
        $keys = $this->keys('hash.' . $hash . '.*');

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

    /**
     * @return Cachesql
     */
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

    /**
     * @param $hash
     * @param $key
     *
     * @return bool
     */
    public function sismember($hash, $key)
    {
        return in_array($key, $this->get($hash, []));
    }

    /**
     * @param string $key
     *
     * @return array
     */
    public function smembers(string $key): array
    {
        return $this->get($key, []);
    }

    /**
     * @param string $hash
     * @param string $key
     *
     * @return bool
     */
    public function srem(string $hash, string $key)
    {
        $tab = $this->get($hash, []);

        $new = [];

        $exists = false;

        foreach ($tab as $row) {
            if ($row !== $key) {
                $new[] = $row;
            } else {
                $exists = true;
            }
        }

        if (true === $exists) {
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

        if ($val !== 'octodummy') {
            $this->set($key, $val);
        } else {
            $val = $this->get($key);
            $this->delete($key);
        }

        return $val !== 'octodummy' ? $this : $val;
    }

    public function add($k, $v, $e)
    {
        if (!$this->has($k)) {
            return $this->set($k, $v, $e);
        }

        return $this;
    }

    public function setNow($k, $v, $expire = null)
    {
        $file = $this->getPath($k);

        $this->_delete($file);

        $v = value($v);

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
        return $e ?: Registry::get('cache.ttl.' . $this->id, $e);
    }

    /**
     * @param string $key
     * @param $value
     * @return Caching
     */
    public function append(string $key, $value)
    {
        $array = $this->get($key, []);

        $array[] = $value;

        return $this->set($key, $array);
    }

    private function _delete(string $k)
    {
        return BreezeModel::where('k', $k)->delete();
    }

    private function _put(string $k, $v, $e)
    {
        BreezeModel::create(compact('k', 'v', 'e'));
    }

    private function _read(string $k)
    {
        $row = BreezeModel::where('k', $k)->first();

        if ($row) {
            return ['v' => $row->v, 'e' => (int) $row->e];
        }

        return false;
    }

    private function _exists(string $k)
    {
        return BreezeModel::where('k', $k)->count() === 1;
    }
}