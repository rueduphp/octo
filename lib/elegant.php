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
     *
     * @throws \ReflectionException
     */
    public static function __callStatic($m, $a)
    {
        $callable = [gi()->make(get_called_class()), $m];

        $params = array_merge($callable, $a);

        return instanciator()->call(...$params);
    }

    /**
     * @param string $m
     * @param array $a
     *
     * @return mixed|null
     *
     * @throws \ReflectionException
     */
    public function __call($m, $a)
    {
        $class = get_called_class();

        if (!isset($this->__capsule)) {
            $this->__capsule = Capsule::getInstance()->model($class);
        }

        if (in_array($m, ['increment', 'decrement'])) {
            return $this->$m(...$a);
        }

        $callable = [$this->newQuery(), $m];

        $params = array_merge($callable, $a);

        return instanciator()->call(...$params);
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
