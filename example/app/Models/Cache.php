<?php
namespace App\Models;

use App\Services\Model;
use ArrayAccess;
use Octo\Collection;
use Symfony\Component\Yaml\Exception\DumpException;
use Symfony\Component\Yaml\Yaml;

class Cache extends Model implements ArrayAccess
{
    protected $namespace      = 'core';
    protected $table          = 'kv';
    protected $primaryKey     = 'k';
    protected static $memory  = [];
    protected static $cleaned = [];
    public $timestamps        = false;
    public $incrementing      = false;
    protected $forceCache     = false;
    protected $dates          = ['called_at'];

    public static function boot()
    {
        parent::boot();
        static::clean('core');
    }

    /**
     * @return array
     */
    public function getMemory(): array
    {
        return static::$memory;
    }

    /**
     * @param string $pattern
     * @return array
     */
    public function keys(string $pattern = '*')
    {
        static::clean($this->namespace);

        $pattern = str_replace('*', '%', $pattern);

        $rows = static::select('k')
            ->where('k', 'like', $this->namespace . '.' . $pattern)
            ->get()
        ;

        $collection = [];

        foreach ($rows as $row) {
            array_push($collection, str_replace($this->namespace . '.', '', $row->getAttribute('k')));
        }

        return $collection;
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public function read(string $key, $default = null)
    {
        $key = $this->makeKey($key);

        if (isset(static::$memory[$key])) {
            return static::$memory[$key];
        }

        $row = $this->firstOrCreate(['k' => $key]);

        $value = $row->getAttribute('v');

        $row->update(['called_at' => now()]);

        if (empty($value)) {
            return $default;
        }

        $value = unserialize($value);

        static::$memory[$key] = $value;

        return $value;
    }

    /**
     * @param $key
     * @param int $expire
     * @param int $by
     * @return int
     */
    public function incr($key, $expire = 0, $by = 1)
    {
        $key = $this->makeKey($key);

        $row = $this->firstOrCreate(['k' => $key]);

        $value = $row->getAttribute('v');

        if (empty($value)) {
            $old = 0;
        } else {
            $old = (int) unserialize($value);
        }

        $new = $old + $by;

        $update = ['v' => serialize($new), 'called_at' => now()];

        if (0 < $expire) {
            $update['e'] = time() + ($expire * 60);
        }

        $row->update($update);

        static::$memory[$key] = serialize($new);

        return $new;
    }

    /**
     * @param $key
     * @param int $expire
     * @param int $by
     * @return int
     */
    public function decr($key, $expire = 0, $by = 1)
    {
        return  $this->incr($key, $expire, $by * -1);
    }

    /**
     * @param string $namespace
     * @return mixed
     */
    public static function clean(string $namespace)
    {
        static::$cleaned[$namespace] = true;

        return static::where('e', '>', 0)->where('e', '<', time())->delete();
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        $key = $this->makeKey($offset);

        if (isset(static::$memory[$key])) {
            return true;
        }

        $row = $this->find($key);

        return $row ? true : false;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function __isset($offset)
    {
        $key = $this->makeKey($offset);

        if (isset(static::$memory[$key])) {
            return true;
        }

        $row = $this->find($key);

        return $row ? true : false;
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        $key = $this->makeKey($offset);

        if (isset(static::$memory[$key])) {
            return static::$memory[$key];
        }

        $row = $this->find($key);

        if ($row) {
            $row->update(['called_at' => now()]);

            $value = unserialize($row->getAttribute('v'));
            static::$memory[$key] = $value;

            return $value;
        }

        return null;
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     */
    public function __get($offset)
    {
        $key = $this->makeKey($offset);

        if (isset(static::$memory[$key])) {
            return static::$memory[$key];
        }

        /** @var Model $row */
        $row = $this->find($key);

        if ($row) {
            $row->update(['called_at' => now()]);

            $value = unserialize($row->getAttribute('v'));
            static::$memory[$key] = $value;

            return $value;
        }

        return null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $key = $this->makeKey($offset);

        $row = $this->firstOrCreate(['k' => $key]);
        $serialized = serialize($value);
        $row->update(['v' => $serialized]);

        static::$memory[$key] = $serialized;
    }

    /**
     * @param string $offset
     * @param $value
     * @param int $expire
     * @return $this
     */
    public function expire(string $offset, $value, $expire = 0)
    {
        $e = time() + (60 * $expire);

        $key = $this->makeKey($offset);

        $row = $this->firstOrCreate(['k' => $key]);
        $serialized = serialize($value);
        $row->update(['v' => $serialized, 'e' => $e]);

        static::$memory[$key] = $serialized;

        return $this;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function __set($offset, $value)
    {
        $key = $this->makeKey($offset);

        $row = $this->firstOrCreate(['k' => $key]);
        $serialized = serialize($value);
        $row->update(['v' => $serialized]);

        static::$memory[$key] = $serialized;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset(static::$memory[$key = $this->makeKey($offset)]);

        $this->find($key)->delete();
    }

    /**
     * @param mixed $offset
     */
    public function __unset($offset)
    {
        unset(static::$memory[$key = $this->makeKey($offset)]);

        $this->destroy($key);
    }

    /**
     * @return int
     */
    public function flush(): int
    {
        $i = 0;

        foreach($this->keys() as $key) {
            ++$i;
            unset($this[$key]);
        }

        return $i;
    }

    /**
     * @return array
     */
    public function getAll(): array
    {
        $collection = [];

        foreach($this->keys() as $key) {
            $collection[$key] = $this[$key];
        }

        return $collection;
    }

    /**
     * @return Collection
     */
    public function toCollection(): Collection
    {
        return acoll($this->getAll());
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->getAll();
    }

    /**
     * @param  int $inline
     * @param  int $indent
     * @return string
     * @throws DumpException
     */
    public function toYml($inline = 3, $indent = 2)
    {
        return Yaml::dump($this->toArray(), $inline, $indent, true, false);
    }

    /**
     * @return string
     */
    public function toJson($option = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->getAll(), $option);
    }

    /**
     * @param string $key
     * @return string
     */
    private function makeKey(string $key): string
    {
        if (!isset(static::$cleaned[$this->namespace])) {
            static::clean($this->namespace);
        }

        return $this->namespace . '.' . $key;
    }

    /**
     * @param string $namespace
     * @return Cache
     */
    public function setNamespace(string $namespace): Cache
    {
        $this->namespace = $namespace;

        return $this;
    }
}
