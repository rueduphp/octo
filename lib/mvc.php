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
    public function testing(Component $app, Inflector $i,string  $a)
    {
        return $i::lower($a);
    }
}