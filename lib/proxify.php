<?php
namespace Octo;

class Proxify
{
    /**
     * @var array
     */
    private $___ = [];

    /**
     * @param null $native
     * @param array $args
     */
    public function __construct($native = null, array $args = [])
    {
        if (is_string($native)) {
            $this->___['native'] = maker($native, $args);
        } else {
            $this->___['native'] = $native;
        }

        $this->___['methods']   = [];
        $this->___['data']      = [];
        $this->___['count']     = [];
    }

    /**
     * @param string $method
     * @param callable $callable
     *
     * @return Proxify
     */
    public function _override(string $method, callable $callable): self
    {
        if (!is_callable($callable) || (is_string($callable) && is_callable($callable))) {
            $c = function() use ($callable) {
                return $callable;
            };
        }

        $this->___['methods'][$method] = $callable;

        return $this;
    }

    /**
     * @param string $method
     * @return int
     */
    public function _called(string $method): int
    {
        if (isset($this->___['count'][$method])) {
            return (int) $this->___['count'][$method];
        }

        return 0;
    }

    /**
     * @param string $m
     * @param array $a
     *
     * @return $this|mixed|null
     *
     * @throws \ReflectionException
     */
    public function __call(string $m, array $a)
    {
        if (!isset($this->___['count'][$m])) {
            $this->___['count'][$m] = 0;
        }

        $this->___['count'][$m]++;

        $c = isAke($this->___['methods'], $m, null);

        if (is_callable($c)) {
            $params = !is_array($c) ? array_merge([$c], $a) : array_merge($c, $a);

            return callCallable(...$params);
        } else {
            if (!is_null($this->___['native'])) {
                $params = array_merge([$this->___['native'], $m], $a);

                return instanciator()->call(...$params);
            }
        }

        return null;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function __get(string $key)
    {
        return isAke($this->___['data'], $key, $this->___['native']->{$key});
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function __isset(string $key)
    {
        return null !== isAke($this->___['data'], $key, $this->___['native']->{$key});
    }

    /**
     * @param string $key
     */
    public function __unset(string $key)
    {
        unset($this->___['native']->{$key});
        unset($this->___['data'][$key]);
    }

    /**
     * @param string $key
     * @param $value
     */
    public function __set(string $key, $value)
    {
        try {
            $this->___['native']->{$key} = $value;
        } catch (\Exception $e) {
            $this->___['data'][$key] = $value;
        }
    }

    /**
     * @param array ...$args
     * @return string
     */
    public function __toString(...$args)
    {
        if (in_array('toString', $this->___['methods'])) {
            return $this->toString(...$args);
        }

        if (in_array('__toString', get_class_methods($this->___['native']))) {
            return $this->___['native']->__toString(...$args);
        }

        return get_class($this->___['native']);
    }

    /**
     * @return mixed
     */
    public function __invoke()
    {
        return $this->___['native'];
    }
}