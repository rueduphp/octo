<?php
namespace App\Traits;

trait Optionable
{
    /**
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public function getOption(string $key, $default = null)
    {
        return $this->getStoreOption()->read($key, $default);
    }

    /**
     * @param string $key
     * @param $value
     * @return $this
     */
    public function setOption(string $key, $value)
    {
        $this->getStoreOption()[$key] = $value;

        return $this;
    }

    /**
     * @param string $key
     * @param $value
     * @param int $minutes
     * @return $this
     */
    public function expireOption(string $key, $value, int $minutes = 0)
    {
        $this->getStoreOption()->expire($key, $value, $minutes);

        return $this;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function unsetOption(string $key): bool
    {
        $store = $this->getStoreOption();

        $status = isset($store[$key]);

        unset($store[$key]);

        return $status;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function hasOption(string $key): bool
    {
        return isset($this->getStoreOption()[$key]);
    }

    /**
     * @return array
     */
    public function allOptions()
    {
        return $this->getStoreOption()->getAll();
    }

    /**
     * @return \App\Models\Cache
     */
    protected function getStoreOption()
    {
        $namespace = 'opt.' . str_replace('\\', '.', strtolower(get_called_class()));

        return store($namespace);
    }
}
