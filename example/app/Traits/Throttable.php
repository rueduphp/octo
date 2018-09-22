<?php

namespace App\Traits;

use Octo\FastRequest;

trait Throttable
{
    /**
     * @return bool
     */
    protected function hasTooManyAttempts()
    {
        return $this->limiter()->tooManyAttempts(
            $this->throttleKey(), $this->maxAttempts()
        );
    }

    protected function incrementAttempts()
    {
        $this->limiter()->hit(
            $this->throttleKey(), $this->decayMinutes()
        );
    }

    /**
     * @return void
     */
    protected function clearAttempts()
    {
        $this->limiter()->clear($this->throttleKey());
    }

    /**
     * @return string
     */
    protected function throttleKey()
    {
        /** @var FastRequest $request */
        $request = main()->container()['main.request'];

        $user = main()->user('id') ?? 'guest';

        return sha1($user . '|' . $request->ip());
    }

    /**
     * @return \Illuminate\Cache\RateLimiter
     */
    protected function limiter()
    {
        return main()->container()['throttle'];
    }

    /**
     * @return int
     */
    public function maxAttempts()
    {
        return $this->maxAttempts ?? 5;
    }

    /**
     * @return int
     */
    public function decayMinutes()
    {
        return $this->decayMinutes ?? 1;
    }
}
