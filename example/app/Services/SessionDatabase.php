<?php

namespace App\Services;

use Carbon\Carbon;
use Octo\Facades\Config as CoreConf;
use SessionHandlerInterface;

class SessionDatabase implements SessionHandlerInterface
{
    /**
     * @var \Illuminate\Database\ConnectionInterface
     */
    protected $connection;

    /**
     * @var string
     */
    protected $table;

    /**
     * @var int
     */
    protected $minutes;

    /**
     * @var bool
     */
    protected $exists;

    /**
     * @param string $table
     * @param int $minutes
     */
    public function __construct(string $table = 'sessions', int $minutes = 120)
    {
        $config = CoreConf::get('db');
        $this->table = $table;
        $this->minutes = $minutes;
        $this->connection = dic('db')->connection($config['default']);
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
        $session = (object) $this->getQuery()->find($sessionId);

        if ($this->expired($session)) {
            $this->exists = true;

            return '';
        }

        if (isset($session->payload)) {
            $this->exists = true;

            return unserialize($session->payload);
        }

        return '';
    }

    /**
     * @param $session
     * @return bool
     */
    protected function expired($session)
    {
        return isset($session->last_activity) &&
            $session->last_activity < Carbon::now()->subMinutes($this->minutes)->getTimestamp();
    }

    /**
     * @param string $sessionId
     * @param string $data
     * @return bool
     * @throws \ReflectionException
     */
    public function write($sessionId, $data)
    {
        $payload = $this->getDefaultPayload($data);

        if (!$this->exists) {
            $this->read($sessionId);
        }

        if ($this->exists) {
            $this->performUpdate($sessionId, $payload);
        } else {
            $this->performInsert($sessionId, $payload);
        }

        return $this->exists = true;
    }

    /**
     * @param $sessionId
     * @param $payload
     * @return bool
     */
    protected function performInsert($sessionId, $payload)
    {
        try {
            return $this->getQuery()->insert(\Octo\aset($payload, 'id', $sessionId));
        } catch (\Exception $e) {
            return $this->performUpdate($sessionId, $payload);
        }
    }

    /**
     * @param $sessionId
     * @param $payload
     * @return int
     */
    protected function performUpdate($sessionId, $payload)
    {
        return $this->getQuery()->where('id', $sessionId)->update($payload);
    }

    /**
     * @param $data
     * @return array|\Octo\Tap
     * @throws \ReflectionException
     */
    protected function getDefaultPayload($data)
    {
        $payload = [
            'payload' => serialize($data),
            'last_activity' => Carbon::now()->getTimestamp(),
        ];

        return $payload;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId)
    {
        $this->getQuery()->where('id', $sessionId)->delete();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc($lifetime)
    {
        $this->getQuery()->where('last_activity', '<=', Carbon::now()->getTimestamp() - $lifetime)->delete();
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    protected function getQuery()
    {
        return $this->connection->table($this->table);
    }

    /**
     * @param $value
     * @return $this
     */
    public function setExists($value)
    {
        $this->exists = $value;

        return $this;
    }
}
