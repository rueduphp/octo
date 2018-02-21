<?php
namespace Octo;

use Illuminate\Database\Eloquent\Model as EloquentModel;

class Elegant extends EloquentModel implements FastModelInterface
{
    protected $guarded  = [];
    protected $__capsule;

    /**
     * @param string $m
     * @param array $a
     *
     * @return mixed
     */
    public static function __callStatic($m, $a)
    {
        return (new static)->$m(...$a);
    }

    /**
     * @param string $m
     * @param array $a
     *
     * @return mixed
     */
    public function __call($m, $a)
    {
        $class = get_called_class();

        if (!isset($this->__capsule)) {
            $this->__capsule = Capsule::instance()->model($class);
        }

        if (in_array($m, ['increment', 'decrement'])) {
            return $this->$m(...$a);
        }

        return $this->newQuery()->$m(...$a);
    }

    /**
     * @return FastFactory
     */
    public static function factory()
    {
        $class = get_called_class();

        return new FastFactory($class, new $class());
    }
}
