<?php
namespace Octo;

use Illuminate\Cache\FileStore;

class Store extends FileStore
{
    /**
     * @param string $key
     * @param $value
     * @param int $minutes
     *
     * @return Store
     */
    public function set(string $key, $value, int $minutes = 0): self
    {
        $this->put($key, $value, $minutes);

        return $this;
    }

    /**
     * @param string $key
     * @param $value
     *
     * @return Store
     */
    public function keep(string $key, $value): self
    {
        return $this->set($key, $value);
    }

    /**
     * @param array|string $key
     * @param null $default
     * @return mixed|null
     */
    public function get($key, $default = null)
    {
        return $this->getPayload($key)['data'] ?? $default;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return 'octodummy' !== $this->get($key, 'octodummy');
    }

    /**
     * @param $key
     * @return bool
     */
    public function delete($key): bool
    {
        return $this->forget($key);
    }

    /**
     * @param $key
     * @return bool
     */
    public function del($key): bool
    {
        return $this->forget($key);
    }
}
