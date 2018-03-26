<?php

namespace Octo;

class Lazy
{
    /**
     *
     * The callable to invoke.
     *
     * @var callable
     *
     */
    protected $callable;

    /**
     *
     * Arguments for the callable.
     *
     * @var array
     *
     */
    protected $params;

    /**
     * @var bool
     */
    protected $called = false;

    /**
     * @param callable $callable The callable to invoke.
     * @param array $params Arguments for the callable.
     */
    public function __construct($callable, array $params = [])
    {
        if (!is_callable($callable)) {
            $callable = new self(Resolver::resolver($callable));
        }

        $this->callable = $callable;
        $this->params   = $params;
    }

    /**
     * @return object The object created by the closure.
     */
    public function __invoke()
    {
        if (false === $this->called) {
            if (is_array($this->callable)) {
                foreach ($this->callable as $key => $val) {
                    if ($val instanceof self) {
                        $this->callable[$key] = $val();
                    }
                }
            } elseif ($this->callable instanceof self) {
                $this->callable = $this->callable->__invoke();
            }

            foreach ($this->params as $key => $value) {
                if ($value instanceof self) {
                    $this->params[$key] = $value();
                }
            }

            $this->called = true;
        }

        if (is_callable($this->callable)) {
            return call_user_func_array($this->callable, $this->params);
        }

        return $this->callable;
    }
}
