<?php

namespace Octo;

use function class_exists;
use function func_get_args;

class Fastcontainer implements FastContainerInterface
{
    /**
     * @param string $key
     * @param mixed $value
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

        if ('octodummy' == $value) {
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
     * @param string $key
     *
     * @return bool
     */
    public function datahas($key)
    {
        return array_key_exists($key, Registry::get('core.Fastcontainer.data'));
    }

    /**
     * @param string $key
     * @param mixed $value
     *
     * @return $this
     */
    public function dataset($key, $value)
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
    public function datadel($key)
    {
        if ($this->datahas($key)) {
            $data = Registry::get('core.Fastcontainer.data', []);

            unset($data[$key]);

            Registry::set('core.Fastcontainer.data', $data);
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function call()
    {
        return call_user_func_array('\\Octo\\callMethod', func_get_args());
    }

    /**
     * @return mixed
     */
    public function factory()
    {
        return call_user_func_array('\\Octo\\foundry', func_get_args());
    }

    /**
     * @return mixed
     */
    public function resolve()
    {
        return call_user_func_array('\\Octo\\foundry', func_get_args());
    }

    /**
     * @param string $concern
     * @param null $singleton
     *
     * @return mixed
     */
    public function get($concern, $singleton = null)
    {
        if (class_exists($concern) || interface_exists($concern) || fnmatch('*\\*', $concern)) {
            return $singleton ? maker($concern) : foundry($concern);
        }

        return $this->dataget($concern, $singleton);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        return
            array_key_exists($key, Registry::get('core.Fastcontainer.registered'))
        ||
            class_exists($key) || interface_exists($key) || fnmatch('*\\*', $key)
        ||
            $this->datahas($key);
    }

    /**
     * @return mixed
     */
    public function singleton()
    {
        return call_user_func_array('\\Octo\\maker', func_get_args());
    }

    /**
     * @param string $concern
     * @param callable $callable
     * @param null $c
     *
     * @return $this
     */
    public function register($concern, callable $callable, $c = null)
    {
        $data = Registry::get('core.Fastcontainer.registered', []);

        $data[$concern] = true;

        Registry::set('core.Fastcontainer.registered', $data);

        wire($concern, $callable);

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
     * @param string $concern
     * @param callable $callable
     *
     * @return Fastcontainer
     */
    public function set($concern, callable $callable)
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

    /**
     * @param string $m
     * @param array $a
     *
     * @return mixed
     */

    public function __call($m, $a)
    {
        $method = '\\Octo\\' . $m;

        if (function_exists($method)) {
            return call_user_func_array($method, $a);
        }
    }
}
