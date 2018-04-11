<?php
namespace Octo;

class Mvc
{
    use Componentable;

    /**
     * @param Inflector $i
     * @param string $a
     * @return mixed|null|string|string[]
     */
    public function testing(string  $a, Inflector $i, Component $app)
    {
        return $i::lower($a);
    }
}