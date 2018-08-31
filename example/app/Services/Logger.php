<?php
namespace App\Services;

use App\Traits\Loggable;

class Logger
{
    use Loggable;

    /** @var string */
    protected $namespace = 'core';

    /**
     * @param string $namespace
     * @return Logger
     */
    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return Logger
     */
    public function __call(string $name, array $arguments): self
    {
        return $this->logIt($name, ...$arguments);
    }
}
