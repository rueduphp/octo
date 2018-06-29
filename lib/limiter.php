<?php
namespace Octo;

class Limiter
{
    /** @var Cache  */
    protected $cache;

    /**
     * @param null $driver
     * @throws Exception
     */
    public function __construct($driver = null)
    {
        $driver = is_null($driver) ? fmr('throttle') : $driver;

        $this->cache = $driver;
    }

    /**
     * @param string $key
     * @param int $maxAttempts
     * @param int $delayMinutes
     *
     * @return bool
     *
     * @throws Exception
     * @throws \Exception
     */
    public function tooManyAttempts(string $key, int $maxAttempts, int $delayMinutes = 1)
    {
        if ($this->cache->has($key . '.lockout')) {
            return true;
        }

        if ($this->attempts($key) > $maxAttempts) {
            $this->cache->set($key . '.lockout', time() + ($delayMinutes * 60), $delayMinutes);

            $this->resetAttempts($key);

            return true;
        }

        return false;
    }

    /**
     * @param string $key
     * @param int $delayMinutes
     *
     * @return int
     *
     * @throws Exception
     * @throws \Exception
     */
    public function hit(string $key, int $delayMinutes = 1)
    {
        $value = (int) $this->cache->increment($key);

        $this->cache->expire($key, $delayMinutes);

        return $value;
    }

    /**
     * @param string $key
     *
     * @return int
     *
     * @throws Exception
     * @throws \Exception
     */
    public function attempts(string $key)
    {
        return $this->cache->get($key, 0);
    }

    /**
     * @param string $key
     *
     * @return bool
     *
     * @throws Exception
     * @throws \Exception
     */
    public function resetAttempts(string $key)
    {
        return $this->cache->forget($key);
    }

    /**
     * @param string $key
     * @param int $maxAttempts
     *
     * @return int
     *
     * @throws Exception
     * @throws \Exception
     */
    public function retriesLeft(string $key, int $maxAttempts)
    {
        $attempts = $this->attempts($key);

        return $attempts === 0 ? $maxAttempts : $maxAttempts - $attempts + 1;
    }

    /**
     * @param string $key
     *
     * @return bool
     *
     * @throws Exception
     * @throws \Exception
     */
    public function clear(string $key)
    {
        $this->resetAttempts($key);

        return $this->cache->forget($key . '.lockout');
    }

    /**
     * @param string $key
     *
     * @return int
     *
     * @throws Exception
     * @throws \Exception
     */
    public function availableIn(string $key)
    {
        return $this->cache->get($key . '.lockout') - time();
    }
}
