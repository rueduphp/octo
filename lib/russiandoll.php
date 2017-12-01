<?php
namespace Octo;

class Russiandoll
{
    /**
     * @var mixed
     */
    private $cache;

    /**
     * @param mixed $cache
     */
    public function __construct($cache)
    {
        $this->cache = $cache;
    }

    /**
     * @param string $key
     * @param callable $callback
     * @return mixed
     */
    public function cache($key, callable $callback)
    {
        $value = $this->cache->get($key);

        if (!$value) {
            $value = $callback();

            $this->cache->set($key, $value);
        }

        return $value;
    }
}