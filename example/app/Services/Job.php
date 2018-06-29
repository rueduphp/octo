<?php

namespace App\Services;

class Job extends Jobber
{
    protected $redis;
    protected $job;
    protected $decoded;
    protected $reserved;

    public function __construct($container, $redis, $job, $reserved, $connectionName, $queue)
    {
        $this->job = $job;
        $this->redis = $redis;
        $this->queue = $queue;
        $this->reserved = $reserved;
        $this->container = $container;
        $this->connectionName = $connectionName;

        $this->decoded = $this->payload();
    }

    public function getRawBody()
    {
        return $this->job;
    }

    public function delete()
    {
        parent::delete();

        $this->redis->deleteReserved($this->queue, $this);
    }

    public function release($delay = 0)
    {
        parent::release($delay);

        $this->redis->deleteAndRelease($this->queue, $this, $delay);
    }

    public function attempts()
    {
        return ($this->decoded['attempts'] ?? null) + 1;
    }

    public function getJobId()
    {
        return $this->decoded['id'] ?? null;
    }

    public function getRedisQueue()
    {
        return $this->redis;
    }

    public function getReservedJob()
    {
        return $this->reserved;
    }
}
