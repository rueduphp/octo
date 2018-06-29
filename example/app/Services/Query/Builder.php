<?php

namespace App\Services\Query;

use DateTime;

class Builder extends \Illuminate\Database\Query\Builder
{
    /**
     * @var string
     */
    protected $cacheKey;

    /**
     * @var int
     */
    protected $cacheMinutes;

    /**
     * @var string
     */
    protected $cacheDriver;

    /**
     * @var string
     */
    protected $cachePrefix = 'rememberable';

    /**
     * @var bool
     */
    protected $mustCache = true;

    /**
     * @param array $columns
     * @return \Illuminate\Support\Collection|mixed
     * @throws \ReflectionException
     */
    public function get($columns = ['*'])
    {
        if (!is_null($this->cacheMinutes) && true === $this->mustCache) {
            return $this->getCached($columns);
        }

        return parent::get($columns);
    }

    /**
     * @param array $columns
     * @return mixed
     * @throws \ReflectionException
     */
    public function getCached(array $columns = ['*'])
    {
        if (is_null($this->columns)) {
            $this->columns = $columns;
        }

        list($key, $minutes) = $this->getCacheInfo();

        $cache = $this->getCache();

        $callback = $this->getCacheCallback($columns);

        if ($minutes instanceof DateTime || $minutes > 0) {
            return $cache->remember($key, $minutes, $callback);
        }

        return $cache->rememberForever($key, $callback);
    }

    /**
     * @param  \DateTime|int  $minutes
     * @param  string|null  $key
     * @return Builder
     */
    public function remember($minutes = null, ?string $key = null): self
    {
        if (is_int($minutes) || $minutes instanceof DateTime) {
            $this->cacheMinutes = $minutes;
        }

        if (is_string($key)) {
            $this->cacheKey = $key;
        }

        return $this;
    }

    /**
     * @param null|string $key
     * @return Builder
     */
    public function rememberForever(?string $key = null): self
    {
        return $this->remember(-1, $key);
    }

    /**
     * @return Builder
     */
    public function dontRemember(): self
    {
        $this->cacheMinutes = $this->cacheKey = null;

        return $this;
    }

    /**
     * @return Builder
     */
    public function doNotRemember(): self
    {
        return $this->dontRemember();
    }

    /**
     * @param $cacheDriver
     * @return Builder
     */
    public function cacheDriver($cacheDriver): self
    {
        $this->cacheDriver = $cacheDriver;

        return $this;
    }

    /**
     * @return \App\Services\Cache
     */
    protected function getCache()
    {
        $cache = $this->getCacheDriver();

        return $cache;
    }

    /**
     * @return \App\Services\Cache
     */
    protected function getCacheDriver()
    {
        return cacheService(60, 'queryCache')->setRedis(l('redis'));
    }

    /**
     * @return array
     */
    protected function getCacheInfo(): array
    {
        return [$this->getCacheKey(), $this->cacheMinutes];
    }

    /**
     * @return string
     */
    public function getCacheKey(): string
    {
        return $this->cachePrefix . ':' . ($this->cacheKey ?: $this->generateCacheKey());
    }

    /**
     * @return string
     */
    public function generateCacheKey(): string
    {
        $name = $this->connection->getName();

        return hash('sha256', $name . $this->toSql() . serialize($this->getBindings()));
    }

    /**
     * @return bool
     */
    public function flushCache(): bool
    {
        $cnx = $this->getCache()->connection();

        $keys = $cnx->keys($this->getCache()->getPrefix() . $this->cachePrefix . ':*');

        $cnx->multi();

        foreach ($keys as $key) {
            $cnx->del($key);
        }

        $cnx->exec();

        return !empty($keys);
    }

    /**
     * @param array $columns
     * @return \Closure
     */
    protected function getCacheCallback(array $columns)
    {
        return function () use ($columns) {
            return parent::get($columns);
        };
    }

    /**
     * @param string $prefix
     * @return Builder
     */
    public function prefix(string $prefix): self
    {
        $this->cachePrefix = $prefix;

        return $this;
    }

    /**
     * @param int $cacheMinutes
     * @return Builder
     */
    public function setCacheMinutes(int $cacheMinutes): self
    {
        $this->cacheMinutes = $cacheMinutes;

        return $this;
    }

    /**
     * @param bool $mustCache
     * @return Builder
     */
    public function setMustCache(bool $mustCache): self
    {
        $this->mustCache = $mustCache;

        return $this;
    }
}
