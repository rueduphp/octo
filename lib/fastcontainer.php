<?php

namespace Octo;

class Fastcontainer implements FastContainerInterface
{
    use FastTrait;
    
    /**
     * @param string|array $key
     * @param mixed $value
     *
     * @return $this
     */
    public function registry($key, $value = 'octodummy')
    {
        /* Polymorphism  */
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->dataset($k, $v);
            }

            return $this;
        }

        if ('octodummy' === $value) {
            return $this->dataget($key);
        }

        return $this->dataset($key, $value);
    }

    /**
     * @param string $key
     * @param null|mixed $default
     *
     * @return mixed
     */
    public function dataget($key, $default = null)
    {
        return isAke(Registry::get('core.Fastcontainer.data', []), $key, $default);
    }

    /**
     * @param $key
     *
     * @return bool
     */
    public function datahas($key): bool
    {
        return 'octodummy' !== isAke(Registry::get('core.Fastcontainer.data', []), $key, 'octodummy');
    }

    /**
     * @param $key
     * @param $value
     *
     * @return Fastcontainer
     */
    public function dataset($key, $value): self
    {
        $data = Registry::get('core.Fastcontainer.data', []);

        $data[$key] = $value;

        Registry::set('core.Fastcontainer.data', $data);

        return $this;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function datadel($key): bool
    {
        if (true === $this->datahas($key)) {
            $data = Registry::get('core.Fastcontainer.data', []);

            unset($data[$key]);

            Registry::set('core.Fastcontainer.data', $data);

            return true;
        }

        return false;
    }

    /**
     * @param array ...$args
     *
     * @return mixed|null
     *
     * @throws \ReflectionException
     */
    public function call(...$args)
    {
        return callMethod(...$args);
    }

    /**
     * @param array ...$args
     *
     * @return mixed|object
     *
     * @throws \ReflectionException
     */
    public function factory(...$args)
    {
        return foundry(...$args);
    }

    /**
     * @param array ...$args
     *
     * @return mixed|object
     *
     * @throws \ReflectionException
     */
    public function resolve(...$args)
    {
        return $this->factory(...$args);
    }

    /**
     * @param string $concern
     * @param mixed $value
     *
     * @return Fastcontainer
     */
    public function set($concern, $value)
    {
        if (
            is_callable($value) &&
            (class_exists($concern) ||
            interface_exists($concern) ||
            fnmatch('*\\*', $concern))
        ) {
            if ($this->datahas($concern)) {
                $this->datadel($concern);
            }

            return $this->register($concern, $value);
        }

        $wires = Registry::get('core.Fastcontainer.registered', []);

        if (array_key_exists($concern, $wires)) {
            unset($wires[$concern]);

            Registry::set('core.Fastcontainer.registered', $wires);
        }

        return $this->dataset($concern, $value);
    }

    /**
     * @param $concern
     * @param null $singleton
     *
     * @return mixed|object
     *
     * @throws \ReflectionException
     */
    public function get($concern, $singleton = null)
    {
        if (
            !$this->datahas($concern) &&
            (class_exists($concern) ||
            interface_exists($concern) ||
            fnmatch('*\\*', $concern))
        ) {
            return $singleton ?
                instanciator()->singleton($concern) :
                instanciator()->factory($concern)
            ;
        }

        return $this->dataget($concern, $singleton);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has($concern)
    {
        if (
            !$this->datahas($concern) &&
            (class_exists($concern) ||
             interface_exists($concern) ||
             fnmatch('*\\*', $concern))
        ) {
            return array_key_exists($concern, Registry::get('core.Fastcontainer.registered', []));
        }

        return $this->datahas($concern);
    }

    /**
     * @return mixed
     */
    public function singleton(...$args)
    {
        return maker(...$args);
    }

    /**
     * @param $concern
     * @param callable $callable
     * @param null $c
     *
     * @return Fastcontainer
     */
    public function register($concern, callable $callable, $c = null): self
    {
        $data = Registry::get('core.Fastcontainer.registered', []);

        $data[$concern] = true;

        Registry::set('core.Fastcontainer.registered', $data);

        instanciator()->wire($concern, $callable);

        if ($c) {
            $c->set($concern, true);
        }

        return $this;
    }

    /**
     * @param string $concern
     * @param callable $callable
     *
     * @return Fastcontainer
     */
    public function define($concern, callable $callable)
    {
        return $this->register($concern, $callable);
    }

    /**
     * @return object
     */
    public function mock()
    {
        $args = func_get_args();

        $mock = $this->resolve(...$args);

        return dyn($mock);
    }

    public function getApp()
    {
        return $this->self('fast');
    }
}
