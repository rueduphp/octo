<?php

namespace App\Services;

class ViewCache
{
    /** @var Cache */
    private $cache;

    /** @var array */
    private $keys = [];

    public function __construct()
    {
        $this->cache = cacheService(60, 'views')->setRedis(l('redis'));
    }

    /**
     * @param $key
     * @return string
     */
    public static function makeKey($key): string
    {
        if ($key instanceof Model) {
            $key = $key->ck();
        }

        if (is_string($key) && class_exists($key) && $object = new $key) {
            if ($object instanceof Model) {
                $key = forward_static_call([$key, 'lastUpdated'])->ck();
            }
        }

        return $key;
    }

    public function has($key)
    {
        $key = static::makeKey($key);

        ob_start();

        $this->keys[] = $key;

        return $this->cache->has($key);
    }

    /**
     * @return mixed
     * @throws \ReflectionException
     */
    public function put()
    {
        $concern = ob_get_clean();

        return $this->cache
            ->rememberForever(array_pop($this->keys), function () use ($concern) {
                return $concern;
            });
    }

    public static function startKh($id)
    {
        $value = store('viewKh')[$id];

        if (empty($value)) {
            ob_start();

            return false;
        }

        return true;
    }

    /**
     * @param $id
     * @param int $duration
     * @return false|mixed|null|string
     */
    public static function endKh($id, int $duration = 60)
    {
        $store = store('viewKh');

        $value = $store[$id];

        if (empty($value)) {
            $value = ob_get_clean();
            $store->expire($id, $value, $duration);
        }

        return $value;
    }

    public static function startDoll($key)
    {
        $key = static::makeKey($key);

        $value = store('doll')[$key];

        if (empty($value)) {
            ob_start();

            return false;
        }

        return true;
    }

    public static function endDoll($key)
    {
        $key = static::makeKey($key);

        $doll = store('doll');

        $value = $doll[$key];

        if (empty($value)) {
            $value = ob_get_clean();
            $doll[$key] = $value;
        }

        return $value;
    }

    /**
     * @return int
     */
    public static function flush()
    {
        $dolls = store('doll')->flush();
        $caches = store('viewKh')->flush();

        return $dolls + $caches;
    }
}
