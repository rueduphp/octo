<?php
namespace Octo;

use function get_class_methods;

class Mvc
{
    public function __invoke()
    {
        $return = [];

        $methods = get_class_methods(__CLASS__);

        foreach ($methods as $method) {
            if ($method !== '__invoke') {
                $callback = function (...$args) use ($method) {
                    $params = array_merge([$this, $method], $args);

                    return gi()->call(...$params);
                };

                $return[$method] = $callback;
            }
        }

        return $return;
    }

    /**
     * @param Inflector $i
     * @param string $a
     * @return mixed|null|string|string[]
     */
    public function testing(Inflector $i,string  $a)
    {
        return $i::lower($a);
    }
}