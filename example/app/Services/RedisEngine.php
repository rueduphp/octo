<?php
namespace App\Services;

use Octo\FastStorageInterface;

class RedisEngine implements FastStorageInterface
{
    public function __call(string $method, array $parameters)
    {
        return redis()->{$method}(...$parameters);
    }
}
