<?php
namespace App\Services;

use function Octo\callCallable;
use Octo\FastStorageInterface;
use function Octo\gi;
use Octo\Inflector;
use Octo\Registry;

class Data implements FastStorageInterface
{
    private $dir;
    private $id;
    private static $instances = [];

    private function getPath($k)
    {
        return $this->dir . '.' . $k;
    }

    /**
     * @param $key
     * @param null $default
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function pull($key, $default = null)
    {
        $value = $this->get($key, $default);

        $this->forget($key);

        return $value;
    }

    /**
     * @param string $key
     * @param $value
     * @return Data
     * @throws \ReflectionException
     */
    public function push(string $key, $value)
    {
        $array = $this->get($key, []);

        $array[] = $value;

        return $this->set($key, $array);
    }

    public function __construct(string $ns = 'core')
    {
        $this->dir  = $ns;
        $this->id   = sha1('caching' . $ns);

        $this->cleanCache();
    }

    public static function instance($ns = 'core')
    {
        $key = sha1(serialize(func_get_args()));

        $instance = \Octo\isAke(static::$instances, $key, null);

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

        if (fnmatch('get*', $m)) {
            $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($m, 3)));
            $key                = Inflector::lower($uncamelizeMethod);
            $args               = [$key];

            if (!empty($a)) {
                $args[] = current($a);
            }

            return call_user_func_array([$this, 'get'], $args);
        } elseif (fnmatch('set*', $m)) {
            $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($m, 3)));
            $key                = Inflector::lower($uncamelizeMethod);

            return $this->set($key, current($a));
        } elseif (fnmatch('forget*', $m)) {
            $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($m, 6)));
            $key                = Inflector::lower($uncamelizeMethod);
            $args               = [$key];

            return call_user_func_array([$this, 'erase'], $args);
        }
    }

    /**
     * @param $k
     * @param $condition
     * @param $value
     * @param null $expire
     * @return mixed
     * @throws \ReflectionException
     */
    public function cacheIf($k, $condition, $value, $expire = null)
    {
        $condition  = \Octo\value($condition);
        $value      = \Octo\value($value);

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

    /**
     * @param $k
     * @param $v
     * @param null $expire
     * @return $this
     * @throws \ReflectionException
     */
    public function set($k, $v, $expire = null)
    {
        $file = $this->getPath($k);

        $this->_delete($file);

        $v = \Octo\value($v);

        $this->_put($file, serialize($v), is_null($expire) ? 0 : time() + $expire);

        return $this;
    }

    /**
     * @param $k
     * @param $v
     * @param null $expire
     * @return Data
     * @throws \ReflectionException
     */
    public function put($k, $v, $expire = null)
    {
        return $this->set($k, $v, $expire);
    }

    /**
     * @param array $values
     * @param null $e
     * @return $this
     * @throws \ReflectionException
     */
    public function setMany(array $values, $e = null)
    {
        foreach ($values as $k => $v) {
            $this->set($k, $v, $e);
        }

        return $this;
    }

    /**
     * @param array $keys
     * @return array
     * @throws \ReflectionException
     */
    public function many(array $keys)
    {
        $return = [];

        foreach ($keys as $key) {
            $return[$key] = $this->get($key);
        }

        return $return;
    }

    /**
     * @param $key
     * @param $value
     * @param null $expire
     * @return bool
     * @throws \ReflectionException
     */
    public function setnx($key, $value, $expire = null)
    {
        if (!$this->has($key)) {
            $this->set($key, $value, $expire);

            return true;
        }

        return false;
    }

    /**
     * @param $k
     * @param $v
     * @param $timestamp
     * @return $this
     * @throws \ReflectionException
     */
    public function setExpireAt($k, $v, $timestamp)
    {
        $file = $this->getPath($k);

        $this->_delete($file);

        $this->_put($file, serialize(value($v)), $timestamp);

        return $this;
    }

    /**
     * @param $k
     * @param $v
     * @param $expire
     * @return Data
     * @throws \ReflectionException
     */
    public function setExp($k, $v, $expire)
    {
        return $this->set($k, $v, $expire);
    }

    /**
     * @param $k
     * @param $v
     * @param $expire
     * @return Data
     * @throws \ReflectionException
     */
    public function setExpire($k, $v, $expire)
    {
        return $this->set($k, $v, $expire);
    }

    /**
     * @param $k
     * @param $expire
     * @return Data
     * @throws \ReflectionException
     */
    public function expire($k, $expire)
    {
        $v = $this->get($k);

        return $this->set($k, $v, $expire);
    }

    /**
     * @param $k
     * @param $timestamp
     * @return Data
     * @throws \ReflectionException
     */
    public function expireAt($k, $timestamp)
    {
        $v = $this->get($k);

        return $this->set($k, $v, $timestamp);
    }

    /**
     * @param $k
     * @param null $d
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function get($k, $d = null)
    {
        $file = $this->getPath($k);

        if ($this->_exists($file)) {
            $row = $this->_read($file);

            if ($row) {
                $age = $row['e'];

                if (0 === $age || $age >= time()) {
                    return \Octo\value(unserialize($row['v']));
                } else {
                    $this->_delete($file);
                }
            }
        }

        return \Octo\value($d);
    }

    /**
     * @param string $k
     * @param callable $c
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function forever(string $k, callable $c)
    {
        return $this->getOr($k, $c);
    }

    /**
     * @param string $k
     * @param \Closure $c
     * @param null $e
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function getOr(string $k, \Closure $c, $e = null)
    {
        $res = $this->get($k, 'octodummy');

        if ('octodummy' === $res) {
            $this->set($k, $res = gi()->makeClosure($c), $e);
        }

        return $res;
    }

    /**
     * @param string $k
     * @param $c
     * @param null $e
     * @return mixed|null
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
     * @return bool
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

    /**
     * @param $k
     * @param string $v
     * @param null $e
     * @return Data|mixed|null
     * @throws \ReflectionException
     */
    public function session($k, $v = 'dummyget', $e = null)
    {
        $user       = session()->user();
        $isLogged   = !is_null($user);
        $key        = $isLogged
            ? sha1(\Octo\lng() . '.' . \Octo\forever() . '1.' . $k)
            : sha1(\Octo\lng() . '.' . \Octo\forever() . '0.' . $k);

        return 'dummyget' == $v ? $this->get($key) : $this->set($key, $v, $e);
    }

    /**
     * @param $k
     * @param string $v
     * @param null $e
     * @return Data|mixed|null
     * @throws \ReflectionException
     */
    public function my($k, $v = 'dummyget', $e = null)
    {
        $user       = \Octo\my('web')->getUser();
        $isLogged   = !is_null($user);
        $key        = $isLogged
            ? sha1(\Octo\lng() . '.' . \Octo\forever() . '1.' . $k)
            : sha1(\Octo\lng() . '.' . \Octo\forever() . '0.' . $k);

        return 'dummyget' == $v ? $this->get($key) : $this->set($key, $v, $e);
    }

    /**
     * @param $k
     * @param callable $c
     * @param $a
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function aged($k, callable $c, $a)
    {
        $k = sha1($this->dir) . '.' . $k;

        return $this->until($k, $c, $a);
    }

    /**
     * @param $k
     * @return bool
     * @throws \ReflectionException
     */
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

    /**
     * @param $k
     * @return mixed|null
     * @throws \ReflectionException
     */
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

    /**
     * @param $k
     * @return bool
     * @throws \ReflectionException
     */
    public function delete($k)
    {
        $file = $this->getPath($k);

        if ($this->_exists($file)) {
            $this->_delete($file);

            return true;
        }

        return false;
    }

    /**
     * @param $k
     * @return bool
     * @throws \ReflectionException
     */
    public function del($k)
    {
        return $this->delete($k);
    }

    /**
     * @param $k
     * @return bool
     * @throws \ReflectionException
     */
    public function remove($k)
    {
        return $this->delete($k);
    }

    /**
     * @param $k
     * @return bool
     * @throws \ReflectionException
     */
    public function forget($k)
    {
        return $this->delete($k);
    }

    /**
     * @param $k
     * @return bool
     * @throws \ReflectionException
     */
    public function destroy($k)
    {
        return $this->delete($k);
    }

    /**
     * @param $k
     * @param int $by
     * @return int|mixed|null
     * @throws \ReflectionException
     */
    public function incr($k, $by = 1)
    {
        $old = $this->get($k, 0);
        $new = $old + $by;

        $this->set($k, $new);

        return $new;
    }

    /**
     * @param $k
     * @param int $by
     * @return int|mixed|null
     * @throws \ReflectionException
     */
    public function increment($k, $by = 1)
    {
        return $this->incr($k, $by);
    }

    /**
     * @param $k
     * @param int $by
     * @return int|mixed|null
     * @throws \ReflectionException
     */
    public function decr($k, $by = 1)
    {
        $old = $this->get($k, 0);
        $new = $old - $by;

        $this->set($k, $new);

        return $new;
    }

    /**
     * @param $k
     * @param int $by
     * @return int|mixed|null
     * @throws \ReflectionException
     */
    public function decrement($k, $by = 1)
    {
        return $this->decr($k, $by);
    }

    /**
     * @return int
     */
    public function cleanCache()
    {
        return $this->store()->where('e', '>', 0)->where('e', '<', time())->delete();
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
     * @return \Octo\Collection
     */
    public function toCollection()
    {
        return \Octo\coll($this->all());
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->all();
    }

    /**
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->all(), JSON_PRETTY_PRINT);
    }

    /**
     * @param string $pattern
     * @return array
     */
    public function keys(string $pattern = '*')
    {
        $this->cleanCache();

        $pattern = str_replace('*', '%', $pattern);

        $rows = $this->store()->select('k')
            ->where('k', 'like', $this->dir . '.' . $pattern)
            ->get()
        ;

        $collection = [];

        foreach ($rows as $row) {
            array_push($collection, str_replace($this->dir . '.', '', $row->k));
        }

        return $collection;
    }

    /**
     * @param string $pattern
     * @return int
     * @throws \ReflectionException
     */
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

    /**
     * @param string $pattern
     * @return int
     * @throws \ReflectionException
     */
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

                    $affected++;
                }
            }
        }

        return $affected;
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function readAndDelete(string $key, $default = null)
    {
        if ($this->has($key)) {
            $value = $this->get($key);

            $this->delete($key);

            return $value;
        }

        return $default;
    }

    /**
     * @param string $keyFrom
     * @param string $keyTo
     * @param null $default
     * @return Data
     * @throws \ReflectionException
     */
    public function rename(string $keyFrom, string $keyTo, $default = null)
    {
        $value = $this->readAndDelete($keyFrom, $default);

        return $this->set($keyTo, $value);
    }

    /**
     * @param string $keyFrom
     * @param string $keyTo
     * @return Data
     * @throws \ReflectionException
     */
    public function copy(string $keyFrom, string $keyTo)
    {
        return $this->set($keyTo, $this->get($keyFrom));
    }

    /**
     * @param string $key
     * @return int
     * @throws \ReflectionException
     */
    public function getSize(string $key)
    {
        return $this->has($key) ? strlen($this->get($key)) : 0;
    }

    /**
     * @param string $key
     * @return int
     * @throws \ReflectionException
     */
    public function length(string $key)
    {
        return strlen($this->get($key));
    }

    /**
     * @param string $hash
     * @param string $key
     * @param $value
     * @return Data
     * @throws \ReflectionException
     */
    public function hset(string $hash, string $key, $value)
    {
        $key = "hash.$hash.$key";

        return $this->set($key, $value);
    }

    /**
     * @param string $hash
     * @param string $key
     * @param $value
     * @return bool
     * @throws \ReflectionException
     */
    public function hsetnx(string $hash, string $key, $value)
    {
        if (!$this->hexists($hash, $key)) {
            $this->hset($hash, $key, $value);

            return true;
        }

        return false;
    }

    /**
     * @param string $hash
     * @param string $key
     * @param null $default
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function hget(string $hash, string $key, $default = null)
    {
        $key = "hash.$hash.$key";

        return $this->get($key, $default);
    }

    /**
     * @param string $hash
     * @param string $key
     * @return int
     * @throws \ReflectionException
     */
    public function hstrlen(string $hash, string $key)
    {
        if ($value = $this->hget($hash, $key)) {
            return strlen($value);
        }

        return 0;
    }

    /**
     * @param string $hash
     * @param string $key
     * @param callable $c
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function hgetOr(string $hash, string $key, callable $c)
    {
        if ($this->hexists($hash, $key)) {
            return $this->hget($hash, $key);
        }

        $res = $c();

        $this->hset($hash, $key, $res);

        return $res;
    }

    /**
     * @param string $hash
     * @param string $key
     * @param callable|null $exists
     * @param callable|null $notExists
     * @return bool
     * @throws \ReflectionException
     */
    public function hwatch(string $hash, string $key, ?callable $exists = null, ?callable $notExists = null)
    {
        if ($this->hexists($hash, $key)) {
            if (is_callable($exists)) {
                return $exists($this->hget($hash, $key));
            }
        } else {
            if (is_callable($notExists)) {
                return $notExists();
            }
        }

        return false;
    }

    /**
     * @param string $hash
     * @param string $key
     * @param null $default
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function hReadAndDelete(string $hash, string $key, $default = null)
    {
        if ($this->hexists($hash, $key)) {
            $value = $this->hget($hash, $key);

            $this->hdelete($hash, $key);

            return $value;
        }

        return $default;
    }

    /**
     * @param string $hash
     * @param string $key
     * @return bool
     * @throws \ReflectionException
     */
    public function hdelete(string $hash, string $key)
    {
        $key = "hash.$hash.$key";

        return $this->delete($key);
    }

    /**
     * @param string $hash
     * @param string $key
     * @return bool
     * @throws \ReflectionException
     */
    public function hdel(string $hash, string $key)
    {
        return $this->hdelete($hash, $key);
    }

    /**
     * @param string $hash
     * @param string $key
     * @return bool
     * @throws \ReflectionException
     */
    public function hhas(string $hash, string $key)
    {
        $key = "hash.$hash.$key";

        return $this->has($key);
    }

    /**
     * @param string $hash
     * @param string $key
     * @return bool
     * @throws \ReflectionException
     */
    public function hexists(string $hash, string $key)
    {
        return $this->hhas($hash, $key);
    }

    /**
     * @param string $hash
     * @param string $key
     * @param int $by
     * @return int|mixed|null
     * @throws \ReflectionException
     */
    public function hincr(string $hash, string $key, $by = 1)
    {
        $old = $this->hget($hash, $key, 1);
        $new = $old + $by;

        $this->hset($hash, $key, $new);

        return $new;
    }

    /**
     * @param string $hash
     * @param string $key
     * @param int $by
     * @return int|mixed|null
     * @throws \ReflectionException
     */
    public function hdecr(string $hash, string $key, $by = 1)
    {
        $old = $this->hget($hash, $key, 1);
        $new = $old - $by;

        $this->hset($hash, $key, $new);

        return $new;
    }

    /**
     * @param string $hash
     * @return \Generator
     * @throws \ReflectionException
     */
    public function hgetall(string $hash)
    {
        $keys = $this->keys('hash.' . $hash . '.*');

        foreach ($keys as $row) {
            $key = str_replace([$this->dir . '.', "hash.$hash."], '', Arrays::last(explode(DS, $row)));

            yield $key;
            yield unserialize($this->_read($this->getPath($row))['v']);
        }
    }

    /**
     * @param string $hash
     * @return \Generator
     * @throws \ReflectionException
     */
    public function hvals(string $hash)
    {
        $keys = $this->keys('hash.' . $hash . '.*');

        foreach ($keys as $row) {
            yield unserialize($this->_read($this->getPath($row))['v']);
        }
    }

    /**
     * @param string $hash
     * @return int
     */
    public function hlen(string $hash)
    {
        $keys = $this->keys('hash.' . $hash . '.*');

        return count($keys);
    }

    /**
     * @param string $hash
     * @return bool
     * @throws \ReflectionException
     */
    public function hremove(string $hash)
    {
        $keys = $this->keys('hash.' . $hash . '.*');

        foreach ($keys as $row) {
            $this->_delete($this->getPath($row));
        }

        return true;
    }

    /**
     * @param string $hash
     * @return \Generator
     */
    public function hkeys(string $hash)
    {
        $keys = $this->keys('hash.' . $hash . '.*');

        foreach ($keys as $row) {
            $key = str_replace([$this->dir . '.', "hash.$hash."], '', Arrays::last(explode(DS, $row)));

            yield $key;
        }
    }

    /**
     * @param string $key
     * @param $value
     * @return Data
     * @throws \ReflectionException
     */
    public function sadd(string $key, $value)
    {
        $tab = $this->get($key, []);
        $tab[] = $value;

        return $this->set($key, $tab);
    }

    /**
     * @param string $key
     * @return int
     * @throws \ReflectionException
     */
    public function scard(string $key)
    {
        $tab = $this->get($key, []);

        return count($tab);
    }

    /**
     * @return array
     * @throws \ReflectionException
     */
    public function sinter(...$args)
    {
        $tab = [];

        foreach ($args as $key) {
            $tab = array_intersect($tab, $this->get($key, []));
        }

        return $tab;
    }

    /**
     * @return array
     * @throws \ReflectionException
     */
    public function sunion(...$args)
    {
        $tab = [];

        foreach ($args as $key) {
            $tab = array_merge($tab, $this->get($key, []));
        }

        return $tab;
    }

    /**
     * @return Data
     * @throws \ReflectionException
     */
    public function sinterstore(...$args)
    {
        $destination = array_shift($args);

        $tab = [];

        foreach ($args as $key) {
            $tab = array_intersect($tab, $this->get($key, []));
        }

        return $this->set($destination, $tab);
    }

    /**
     * @return Data
     * @throws \ReflectionException
     */
    public function sunionstore(...$args)
    {
        $destination = array_shift($args);

        $tab = [];

        foreach ($args as $key) {
            $tab = array_merge($tab, $this->get($key, []));
        }

        return $this->set($destination, $tab);
    }

    /**
     * @param string $hash
     * @param string $key
     * @return bool
     * @throws \ReflectionException
     */
    public function sismember(string $hash, string $key)
    {
        return in_array($key, $this->get($hash, []));
    }

    /**
     * @param string $key
     * @return array
     * @throws \ReflectionException
     */
    public function smembers(string $key): array
    {
        return $this->get($key, []);
    }

    /**
     * @param string $hash
     * @param string $key
     * @return bool
     * @throws \ReflectionException
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

    /**
     * @param string $from
     * @param string $to
     * @param string $key
     * @return bool
     * @throws \ReflectionException
     */
    public function smove(string $from, string $to, string $key)
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

    /**
     * @param $k
     * @param callable $c
     * @param null $maxAge
     * @param array $args
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function until($k, callable $c, $maxAge = null, $args = [])
    {
        if (defined('testing') && testing === true) {
            return call_user_func_array($c, $args);
        }

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

    /**
     * @param string $key
     * @param string $val
     * @return Data|mixed|null|string
     * @throws \ReflectionException
     */
    public function flash(string $key, $val = 'octodummy')
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

    /**
     * @param string $key
     * @param $v
     * @param $e
     * @return Data
     * @throws \ReflectionException
     */
    public function add(string $key, $v, $e): self
    {
        if (!$this->has($key)) {
            return $this->set($key, $v, $e);
        }

        return $this;
    }

    /**
     * @param string $key
     * @param $v
     * @param null $expire
     * @return Data
     * @throws \ReflectionException
     */
    public function setNow(string $key, $v, $expire = null): self
    {
        $file = $this->getPath($key);

        $this->_delete($file);

        $v = \Octo\value($v);

        $this->_put($file, serialize($v), is_null($expire) ? time() : time() + $expire);

        return $this;
    }

    /**
     * @param string $key
     * @return bool
     * @throws \ReflectionException
     */
    public function hasNow(string $key)
    {
        return $this->_exists($this->getPath($key));
    }

    /**
     * @param string $key
     * @param null $d
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function getNow(string $key, $d = null)
    {
        $file = $this->getPath($key);

        if ($this->_exists($file)) {
            return unserialize($this->_read($file)['v']);
        }

        return $d;
    }

    /**
     * @param string $key
     * @return bool
     * @throws \ReflectionException
     */
    public function delNow(string $key)
    {
        $file = $this->getPath($key);

        if ($this->_exists($file)) {
            $this->_delete($file);

            return true;
        }

        return false;
    }

    /**
     * @param string $key
     * @return int|mixed
     * @throws \ReflectionException
     */
    public function ageNow(string $key)
    {
        $file = $this->getPath($key);

        if ($this->_exists($file)) {
            $row = $this->_read($file);

            if ($row) {
                return $row['e'];
            }
        }

        return time();
    }

    /**
     * @param string $key
     * @param null $d
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function getDel(string $key, $d = null)
    {
        $value = $this->get($key, $d);

        $this->delete($key);

        return $value;
    }

    /**
     * @param string $key
     * @param null $d
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function start(string $key, $d = null)
    {
        if (!$this->has($key)) {
            Registry::set('cache.buffer.' . $this->id, $key);
            ob_start();

            return $d;
        }

        Registry::delete('cache.buffer.' . $this->id);

        return $this->get($key);
    }

    /**
     * @return bool|string
     * @throws \ReflectionException
     */
    public function end()
    {
        if ($k = Registry::get('cache.buffer.' . $this->id)) {
            $value = ob_get_clean();

            $this->set($k, $value, $this->getTtl());

            return $value;
        }

        return false;
    }

    /**
     * @param null $e
     * @return $this
     */
    public function ttl($e = null)
    {
        if ($e) {
            Registry::set('cache.ttl.' . $this->id, $e);

            return $this;
        }

        return Registry::get('cache.ttl.' . $this->id, $e);
    }

    /**
     * @param null $e
     * @return mixed
     */
    public function getTtl($e = null)
    {
        return $e ?: Registry::get('cache.ttl.' . $this->id, $e);
    }

    /**
     * @param string $key
     * @param $value
     * @return Data
     * @throws \ReflectionException
     */
    public function append(string $key, $value)
    {
        $array = $this->get($key, []);

        $array[] = $value;

        return $this->set($key, $array);
    }

    /**
     * @param string $k
     * @return bool
     */
    private function _delete(string $k)
    {
        return $this->store()->where('k', $k)->delete() > 0;
    }

    /**
     * @param string $k
     * @param $v
     * @param $e
     * @throws \ReflectionException
     */
    private function _put(string $k, $v, $e)
    {
        $this->store()->insert(compact('k', 'v', 'e'));
    }

    /**
     * @param string $k
     * @return array|bool
     * @throws \ReflectionException
     */
    private function _read(string $k)
    {
        $row = $this->store()->where('k', $k)->first();

        if ($row) {
            return ['v' => $row->v, 'e' => (int) $row->e];
        }

        return false;
    }

    /**
     * @param string $k
     * @return bool
     * @throws \ReflectionException
     */
    private function _exists(string $k)
    {
        return $this->store()->where('k', $k)->count() === 1;
    }

    /**
     * @param array $rows
     * @return Data
     * @throws \ReflectionException
     */
    public function fill(array $rows = []): self
    {
        foreach ($rows as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * @param string $ns
     * @return Data
     */
    public function setNS(string $ns): self
    {
        $this->dir  = $ns;
        $this->id   = sha1('caching' . $ns);

        return $this;
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    protected function store()
    {
        return sqlite()->table('d');
    }
}
