<?php
namespace Octo;

class Flash implements FastFlashInterface
{
    /**
     * @var FastSessionInterface
     */
    private $storage;

    /**
     * @var null|string
     */
    private $storageKey = 'sessflash';

    /**
     * @var array
     */
    private $cache = [];

    /**
     * @param FastSessionInterface $storage
     * @param null|string $storageKey
     */
    public function __construct(FastSessionInterface $storage, $storageKey = null)
    {
        $this->storage = $storage;

        if (is_string($storageKey) && $storageKey) {
            $this->storageKey = $storageKey;
        }
    }

    /**
     * @param $key
     * @param $message
     *
     * @return $this
     */
    public function set($key, $message)
    {
        $rows = $this->storage->get($this->storageKey,[]);

        if (!isset($rows[$key])) {
            $rows[$key] = [];
        }

        $rows[$key][] = $message;

        $this->storage->set($this->storageKey, $rows);

        return $this;
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->storage->get($this->storageKey,[]);
    }

    /**
     * @param $key
     * @return bool
     */
    public function has($key)
    {
        $rows = $this->storage->get($this->storageKey,[]);

        return !empty($rows[$key]) || !empty($this->cache[$key]);
    }

    public function get($key, $default = null)
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $messages = $this->all();

        if (isset($messages[$key])) {
            $value = $messages[$key];

            $this->cache[$key] = $value;

            $rows = $this->storage->get($this->storageKey,[]);

            unset($rows[$key]);

            $this->storage->set($this->storageKey, $rows);

            return $value;
        }

        return $default;
    }

    public function first($key, $default = null)
    {
        if ($this->has($key)) {
            $messages = $this->get($key);

            if (is_array($messages) && !empty($messages)) {
                return $messages[0];
            }
        }

        return $default;
    }

    /**
     * @return FastSessionInterface
     */
    public function getStorage(): FastSessionInterface
    {
        return $this->storage;
    }

    /**
     * @return null|string
     */
    public function getStorageKey()
    {
        return $this->storageKey;
    }

    public function __call($name, array $arguments)
    {
        if (0 === count($arguments)) {
            if (fnmatch('has*', $name) && strlen($name) > 3) {
                $key = callField($name, 'has');

                return $this->has($key);
            } else {
                return $this->first($name);
            }
        }

        if (1 === count($arguments)) {
            return $this->set($name, current($arguments));
        }
    }
}