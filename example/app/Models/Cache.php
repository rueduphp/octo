<?php
namespace App\Models;

use App\Services\Model;
use ArrayAccess;
use Octo\Collection;
use ReflectionException;

class Cache extends Model implements ArrayAccess
{
    protected $namespace    = 'core';
    protected $table        = 'kv';
    protected $primaryKey   = 'k';
    public $timestamps      = false;
    public $incrementing    = false;
    protected $forceCache   = false;

    public static function boot()
    {
        parent::boot();
        static::clean();
    }

    public function keys(string $pattern = '*')
    {
        static::clean();

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

        $row = $this->firstOrCreate(['k' => $key]);

        $value = $row->getAttribute('v');

        if (empty($value)) {
            return $default;
        }

        return unserialize($value);
    }

    /**
     * @param $k
     * @param int $by
     * @return int
     */
    public function incr($key, $by = 1)
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

        $row->update(['v' => serialize($new)]);

        return $new;
    }

    /**
     * @param $key
     * @param int $by
     * @return int
     */
    public function decr($key, $by = 1)
    {
        return  $this->incr($key, $by * -1);
    }

    /**
     * @return mixed
     */
    public static function clean()
    {
        return static::where('e', '>', 0)->where('e', '<', time())->delete();
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        $key = $this->makeKey($offset);

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

        $row = $this->find($key);

        if ($row) {
            return unserialize($row->getAttribute('v'));
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

        /** @var Model $row */
        $row = $this->find($key);

        if ($row) {
            return unserialize($row->getAttribute('v'));
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
        $row->update(['v' => serialize($value)]);
    }

    /**
     * @param string $offset
     * @param $value
     * @param int $expire
     */
    public function expire(string $offset, $value, $expire = 0)
    {
        $e = time() + (60 * $expire);

        $key = $this->makeKey($offset);

        $row = $this->firstOrCreate(['k' => $key]);
        $row->update(['v' => serialize($value), 'e' => $e]);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function __set($offset, $value)
    {
        $key = $this->makeKey($offset);

        $row = $this->firstOrCreate(['k' => $key]);
        $row->update(['v' => serialize($value)]);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        $key = $this->makeKey($offset);

        $this->find($key)->delete();
    }

    /**
     * @param mixed $offset
     */
    public function __unset($offset)
    {
        $key = $this->makeKey($offset);

        $this->find($key)->delete();
    }

    /**
     * @return array
     */
    public function alls(): array
    {
        $collection = [];

        foreach($this->keys() as $key) {
            $collection[$key] = unserialize($this->find($this->makeKey($key))->getAttribute('v'));
        }

        return $collection;
    }

    /**
     * @return Collection
     */
    public function toCollection(): Collection
    {
        return acoll($this->alls());
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->alls();
    }

    /**
     * @return string
     */
    public function toJson($option = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->alls(), $option);
    }

    /**
     * @param string $key
     * @return string
     */
    private function makeKey(string $key): string
    {
        static::clean();

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
