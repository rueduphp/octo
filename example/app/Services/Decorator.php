<?php

namespace App\Services;

abstract class Decorator
{
    /**
     * @var mixed
     */
    protected $concern;

    /**
     * @param mixed $concern
     */
    function __construct($concern)
    {
        $this->concern = $concern;
    }

    /**
     * @param $property
     * @return mixed
     */
    public function __get($property)
    {
        if (method_exists($this, $property)) {
            return cf([$this, $property]);
        }

        return $this->concern->{$property};
    }
}
