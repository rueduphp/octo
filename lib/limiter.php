<?php

    namespace Octo;

    class Limiter
    {
        protected $cache;

        public function __construct($friver = null)
        {
            $driver = is_null($driver) ? fmr('throttle') : $driver;

            $this->cache = $driver;
        }

        public function tooManyAttempts($key, $maxAttempts, $decayMinutes = 1)
        {
            if ($this->cache->has($key . '.lockout')) {
                return true;
            }

            if ($this->attempts($key) > $maxAttempts) {
                $this->cache->set($key . '.lockout', time() + ($decayMinutes * 60), ($decayMinutes * 60));

                $this->resetAttempts($key);

                return true;
            }

            return false;
        }

        public function hit($key, $decayMinutes = 1)
        {
            $this->cache->set($key, 1, $decayMinutes);

            return (int) $this->cache->increment($key);
        }

        public function attempts($key)
        {
            return $this->cache->get($key, 0);
        }

        public function resetAttempts($key)
        {
            return $this->cache->forget($key);
        }

        public function retriesLeft($key, $maxAttempts)
        {
            $attempts = $this->attempts($key);

            return $attempts === 0 ? $maxAttempts : $maxAttempts - $attempts + 1;
        }

        public function clear($key)
        {
            $this->resetAttempts($key);

            $this->cache->forget($key . '.lockout');
        }

        public function availableIn($key)
        {
            return $this->cache->get($key . '.lockout') - time();
        }
    }
