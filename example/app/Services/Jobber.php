<?php

namespace App\Services;

use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Octo\Inflector;

abstract class Jobber
{
    protected $instance;
    protected $container;
    protected $deleted = false;
    protected $released = false;
    protected $failed = false;
    protected $connectionName;
    protected $queue;

    abstract public function getJobId();

    abstract public function getRawBody();

    public function fire()
    {
        $payload = $this->payload();

        list($class, $method) = static::parse($payload['job']);

        ($this->instance = $this->resolve($class))->{$method}($this, $payload['data']);
    }

    public function delete()
    {
        $this->deleted = true;
    }

    public function isDeleted()
    {
        return $this->deleted;
    }

    public function release($delay = 0)
    {
        $this->released = true;
    }

    public function isReleased()
    {
        return $this->released;
    }

    public function isDeletedOrReleased()
    {
        return $this->isDeleted() || $this->isReleased();
    }

    public function hasFailed()
    {
        return $this->failed;
    }

    public function markAsFailed()
    {
        $this->failed = true;
    }

    public function failed($e)
    {
        $this->markAsFailed();

        $payload = $this->payload();

        $class = static::parse($payload['job'])[0];

        if (method_exists($this->instance = $this->resolve($class), 'failed')) {
            $this->instance->failed($payload['data'], $e);
        }
    }

    protected function resolve($class)
    {
        return $this->container->make($class);
    }

    public function payload()
    {
        return json_decode($this->getRawBody(), true);
    }

    public function maxTries()
    {
        return $this->payload()['maxTries'] ?? null;
    }

    public function timeout()
    {
        return $this->payload()['timeout'] ?? null;
    }

    public function timeoutAt()
    {
        return $this->payload()['timeoutAt'] ?? null;
    }

    public function getName()
    {
        return $this->payload()['job'];
    }

    public function resolveName()
    {
        return static::resolver($this->getName(), $this->payload());
    }

    public function getConnectionName()
    {
        return $this->connectionName;
    }

    public function getQueue()
    {
        return $this->queue;
    }

    public function getContainer()
    {
        return $this->container;
    }

    protected function secondsUntil($delay)
    {
        $delay = $this->parseDateInterval($delay);

        return $delay instanceof DateTimeInterface
            ? max(0, $delay->getTimestamp() - $this->currentTime())
            : (int) $delay;
    }

    protected function availableAt($delay = 0)
    {
        $delay = $this->parseDateInterval($delay);

        return $delay instanceof DateTimeInterface
            ? $delay->getTimestamp()
            : Carbon::now()->addSeconds($delay)->getTimestamp();
    }

    protected function parseDateInterval($delay)
    {
        if ($delay instanceof DateInterval) {
            $delay = Carbon::now()->add($delay);
        }

        return $delay;
    }

    protected function currentTime()
    {
        return Carbon::now()->getTimestamp();
    }

    public static function parse($job)
    {
        return Inflector::contains($job, '@') ? explode('@', $job, 2) : [$job, 'handle'];
    }

    public static function resolver($name, $payload)
    {
        if (!empty($payload['displayName'])) {
            return $payload['displayName'];
        }

        return $name;
    }
}
