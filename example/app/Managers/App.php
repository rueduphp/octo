<?php
namespace App\Managers;

use App\Traits\Exportable;
use App\Traits\Gettable;
use Traversable;

class App implements \ArrayAccess, \Countable, \IteratorAggregate
{
    use Gettable, Exportable;

    protected $__items = [];
    protected static $_instances = [];

    /**
     * @param string $scope
     * @return App
     */
    public static function getInstance(string $scope = 'main'): self
    {
        if (null === ($instance = static::$_instances[$scope] ?? null)) {
            $instance = new static;
            static::$_instances[$scope] = $instance;
        }

        return $instance;
    }

    /**
     * @return array|Traversable
     */
    public function getIterator()
    {
        return $this->__items;
    }
}
