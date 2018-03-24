<?php

namespace Octo;

use RuntimeException;

class Argon
{
    /**
     * @var int
     */
    protected $memory = 1024;

    /**
     * @var int
     */
    protected $time = 2;

    /**
     * @var int
     */
    protected $threads = 2;

    /**
     * @param string $hashedValue
     * @return array
     */
    public function info(string $hashedValue): array
    {
        return password_get_info($hashedValue);
    }

    /**
     * @param string $value
     * @param array $options
     * @return string
     */
    public function make(string $value, array $options = []): string
    {
        $hash = password_hash($value, PASSWORD_ARGON2I, [
            'memory_cost'   => $this->memory($options),
            'time_cost'     => $this->time($options),
            'threads'       => $this->threads($options),
        ]);

        if ($hash === false) {
            throw new RuntimeException('Argon2 hashing not supported.');
        }

        return $hash;
    }

    /**
     * @param string $value
     * @param string $hashedValue
     * @param array $options
     * @return bool
     */
    public function check(string $value, string $hashedValue, array $options = []): bool
    {
        if (strlen($hashedValue) === 0) {
            return false;
        }

        return password_verify($value, $hashedValue);
    }

    /**
     * @param string $hashedValue
     * @param array $options
     * @return bool
     */
    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return password_needs_rehash($hashedValue, PASSWORD_ARGON2I, [
            'memory_cost' => $this->memory($options),
            'time_cost' => $this->time($options),
            'threads' => $this->threads($options),
        ]);
    }

    /**
     * @param int $memory
     * @return Argon
     */
    public function setMemory(int $memory): self
    {
        $this->memory = $memory;

        return $this;
    }

    /**
     * @param int $time
     * @return Argon
     */
    public function setTime(int $time): self
    {
        $this->time = $time;

        return $this;
    }

    /**
     * @param int $threads
     * @return Argon
     */
    public function setThreads(int $threads): self
    {
        $this->threads = $threads;

        return $this;
    }

    /**
     * @param array $options
     * @return int
     */
    protected function memory(array $options): int
    {
        return $options['memory'] ?? $this->memory;
    }

    /**
     * @param array $options
     * @return int
     */
    protected function time(array $options): int
    {
        return $options['time'] ?? $this->time;
    }

    /**
     * @param array $options
     * @return int
     */
    protected function threads(array $options): int
    {
        return $options['threads'] ?? $this->threads;
    }
}
