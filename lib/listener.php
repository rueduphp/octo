<?php
namespace Octo;

/**
 * Class Listener
 * @package Octo
 */
class Listener implements FastListenerInterface
{
    /**
     * @var int
     */
    public $priority    = 0;

    /**
     * @var bool
     */
    public $halt        = false;

    /**
     * @var bool
     */
    public $once        = false;

    /**
     * @var bool
     */
    public $called      = false;

    /**
     * @var callable
     */
    public $callable;

    /**
     * @param callable $callable
     * @param int $priority
     * @param bool $halt
     * @param bool $once
     */
    public function __construct(
        callable $callable,
        $priority = 0,
        $halt = false,
        $once = false
    ) {
        $this->priority = $priority;
        $this->callable = $callable;
        $this->halt     = $halt;
        $this->once     = $once;
    }

    /**
     * @return $this
     */
    public function once()
    {
        $this->once = !$this->once;

        return $this;
    }

    /**
     * @param bool $halt
     *
     * @return $this
     */
    public function halt(bool $halt = true)
    {
        $this->halt = $halt;

        return $this;
    }

    /**
     * @return $this
     */
    public function called()
    {
        $this->called = !$this->called;

        return $this;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @return bool
     */
    public function isHalt()
    {
        return $this->halt;
    }

    /**
     * @param bool $halt
     * @return $this
     */
    public function setHalt(bool $halt)
    {
        $this->halt = $halt;

        return $this;
    }

    /**
     * @return bool
     */
    public function isOnce()
    {
        return $this->once;
    }

    /**
     * @param bool $once
     *
     * @return Listener
     */
    public function setOnce(bool $once)
    {
        $this->once = $once;

        return $this;
    }

    /**
     * @param bool $called
     * @return Listener
     */
    public function setCalled(bool $called)
    {
        $this->called = $called;

        return $this;
    }

    /**
     * @return bool
     */
    public function isCalled()
    {
        return $this->called;
    }

    /**
     * @return callable
     */
    public function getCallable()
    {
        return $this->callable;
    }

    /**
     * @param callable $callable
     *
     * @return Listener
     */
    public function setCallable(callable $callable)
    {
        $this->callable = $callable;

        return $this;
    }
}
