<?php

namespace App\Services;

use SessionHandlerInterface;

class SessionRedis implements SessionHandlerInterface
{
    /**
     * @var Cache
     */
    protected $cache;

    /**
     * @var int
     */
    protected $minutes;

    /**
     * @param  int  $minutes
     */
    public function __construct(int $minutes = 120)
    {
        $this->cache    = cacheService($minutes, 'session');
        $this->minutes  = $minutes;
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionName)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId)
    {
        return $this->cache->get($sessionId, '');
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $data)
    {
        $this->cache->put($sessionId, $data, $this->minutes);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId)
    {
        $this->cache->forget($sessionId);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc($lifetime)
    {
        return true;
    }

    /**
     * @return Cache
     */
    public function getCache()
    {
        return $this->cache;
    }
}
