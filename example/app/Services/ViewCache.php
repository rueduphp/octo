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

    public function has($key)
    {
        if ($key instanceof Model) {
            $key = $key->ck();
        }

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
}