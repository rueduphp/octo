<?php
namespace App\Models;

class Settings
{
    /**
     * @var string
     */
    protected $namespace;

    /**
     * @var Cache
     */
    protected $store;

    public function __construct(string $namespace = 'core')
    {

        $this->namespace = $namespace;
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public function get(string $key, $default = null)
    {
        if ($this->has($key)) {
            return $this->read($key, $default);
        }

        return $default;
    }

    /**
     * @param string $key
     * @param $value
     * @return Settings
     */
    public function set(string $key, $value): self
    {
        $this->write($key, $value);

        return $this;
    }

    /**
     * @param string $key
     * @param $value
     * @param int $expire
     * @return Settings
     */
    public function expire(string $key, $value, int $expire = 0): self
    {
        $this->store()->expire($key, $value, $expire);

        return $this;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->store()[$key]);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        $status = $this->has($key);
        $this->unwrite($key);

        return $status;
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public function pull(string $key, $default = null)
    {
        $value = $this->get($key, $default);
        $this->delete($key);

        return $value;
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     */
    public function increment(string $key, int $by = 1)
    {
        $this->set($key, $value = $this->get($key, 0) + $by);

        return $value;
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     */
    public function decrement(string $key, int $by = 1)
    {
        return $this->increment($key, $by * -1);
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     */
    public function incr(string $key, int $by = 1)
    {
        return $this->increment($key, $by);
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     */
    public function decr(string $key, int $by = 1)
    {
        return $this->increment($key, $by * -1);
    }

    /**
     * @param string $key
     * @param $value
     */
    protected function write(string $key, $value)
    {
        $this->store()[$key] = $value;
    }


    /**
     * @param string $key
     * @param null $default
     * @return null|mixed
     */
    protected function read(string $key, $default = null)
    {
        return $this->store()->read($key, $default);
    }

    /**
     * @param string $key
     */
    protected function unwrite(string $key)
    {
        unset($this->store()[$key]);
    }

    /**
     * @param string $key
     * @return string
     */
    protected function makeKey(string $key): string
    {
        return 'settings.' . $this->namespace . '.' . $key;
    }

    /**
     * @return Cache
     */
    protected function store()
    {
        return $this->store ?? store();
    }

    /**
     * @param Cache $store
     * @return Settings
     */
    public function setStore(Cache $store): self
    {
        $this->store = $store;

        return $this;
    }

    /**
     * @param string $namespace
     * @return Settings
     */
    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }
}
