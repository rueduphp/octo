<?php

namespace App\Services;

use Illuminate\Container\Container;
use Illuminate\Redis\RedisManager;
use Octo\Inflector;

class Queue extends Queuer
{
    /** @var RedisManager  */
    protected $redis;
    /** @var Container  */
    protected $container;
    protected $connection;
    protected $default;
    protected $retryAfter = 60;
    protected $blockFor = null;

    public function __construct($default = 'default', $connection = null, $retryAfter = 60, $blockFor = null)
    {
        $this->redis        = redis();
        $this->container    = l();
        $this->default      = $default;
        $this->blockFor     = $blockFor;
        $this->connection   = $connection;
        $this->retryAfter   = $retryAfter;
    }

    public function size($queue = null)
    {
        $queue = $this->getQueue($queue);

        return $this->getConnection()->eval(
            Lua::size(), 3, $queue, $queue.':delayed', $queue.':reserved'
        );
    }

    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue);
    }

    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $this->getConnection()->rpush($this->getQueue($queue), $payload);

        return json_decode($payload, true)['id'] ?? null;
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->laterRaw($delay, $this->createPayload($job, $data), $queue);
    }

    protected function laterRaw($delay, $payload, $queue = null)
    {
        $this->getConnection()->zadd(
            $this->getQueue($queue).':delayed', $this->availableAt($delay), $payload
        );

        return json_decode($payload, true)['id'] ?? null;
    }

    protected function createPayloadArray($job, $data = '')
    {
        return array_merge(parent::createPayloadArray($job, $data), [
            'id' => $this->getRandomId(),
            'attempts' => 0,
        ]);
    }

    public function pop($queue = null)
    {
        $this->migrate($prefixed = $this->getQueue($queue));

        list($job, $reserved) = $this->retrieveNextJob($prefixed);

        if ($reserved) {
            return new Job(
                $this->container, $this, $job,
                $reserved, $this->connectionName ?? null, $queue ?: $this->default
            );
        }
    }

    protected function migrate($queue)
    {
        $this->migrateExpiredJobs($queue.':delayed', $queue);

        if (! is_null($this->retryAfter)) {
            $this->migrateExpiredJobs($queue.':reserved', $queue);
        }
    }

    public function migrateExpiredJobs($from, $to)
    {
        return $this->getConnection()->eval(
            Lua::migrateExpiredJobs(), 2, $from, $to, $this->currentTime()
        );
    }

    protected function retrieveNextJob($queue)
    {
        if (!is_null($this->blockFor)) {
            return $this->blockingPop($queue);
        }

        return $this->getConnection()->eval(
            Lua::pop(), 2, $queue, $queue.':reserved',
            $this->availableAt($this->retryAfter)
        );
    }

    protected function blockingPop($queue)
    {
        $rawBody = $this->getConnection()->blpop($queue, $this->blockFor);

        if (!is_null($rawBody)) {
            $payload = json_decode($rawBody[1], true);

            $payload['attempts']++;

            $reserved = json_encode($payload);

            $this->getConnection()->zadd($queue.':reserved', [
                $reserved => $this->availableAt($this->retryAfter),
            ]);

            return [$rawBody[1], $reserved];
        }

        return [null, null];
    }

    public function deleteReserved($queue, $job)
    {
        $this->getConnection()->zrem($this->getQueue($queue).':reserved', $job->getReservedJob());
    }

    public function deleteAndRelease($queue, $job, $delay)
    {
        $queue = $this->getQueue($queue);

        $this->getConnection()->eval(
            Lua::release(), 2, $queue.':delayed', $queue.':reserved',
            $job->getReservedJob(), $this->availableAt($delay)
        );
    }

    protected function getRandomId()
    {
        return Inflector::random(32);
    }

    public function getQueue($queue)
    {
        return 'queues:'.($queue ?: $this->default);
    }

    protected function getConnection()
    {
        return $this->redis;
    }

    public function getRedis()
    {
        return $this->redis;
    }
}
