<?php
namespace Octo;

class Row
{
    private $engine;

    public function __construct($engine)
    {
        $this->engine = $engine;
    }

    /**
     * @param string $key
     * @param $value
     *
     * @return Row
     */
    public function set(string $key, $value): self
    {
        $this->engine->set($key, $value);

        return $this;
    }

    /**
     * @param string $key
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    public function get(string $key, $default = null)
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        return $default;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->engine->has($key);
    }

    /**
     * @param string $key
     * 
     * @return bool
     */
    public function delete(string $key): bool
    {
        if ($this->has($key)) {
            $this->engine->delete($key);

            return true;
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function getEngine()
    {
        return $this->engine;
    }

    /**
     * @param $engine
     *
     * @return Row
     */
    public function setEngine($engine): self
    {
        $this->engine = $engine;

        return $this;
    }
}