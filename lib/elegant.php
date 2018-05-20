<?php
namespace Octo;

use Illuminate\Database\Eloquent\Model as EloquentModel;

class Elegant extends EloquentModel implements FastModelInterface
{
    protected $guarded  = [];
    protected $__capsule;

    /**
     * @param array $attributes
     * @throws \ReflectionException
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (false === booted($class = get_called_class())) {
            bootThis($class);

            $methods = get_class_methods($this);

            if (in_array('events', $methods)) {
                gi()->call($this, 'events', gi()->make(FastEvent::class));
            }

            if (in_array('policies', $methods)) {
                gi()->call($this, 'policies');
            }
        }
    }

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

        return gi()->call(...$params);
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
        if (!isset($this->__capsule)) {
            $this->__capsule = Capsule::getInstance()->model(get_called_class());
        }

        if (in_array($m, ['increment', 'decrement'])) {
            return $this->$m(...$a);
        }

        $callable = [$this->newQuery(), $m];

        $params = array_merge($callable, $a);

        return gi()->call(...$params);
    }

    /**
     * @return FastFactory
     * @throws \ReflectionException
     */
    public static function factory()
    {
        $class = get_called_class();

        return new FastFactory($class, gi()->make($class));
    }
}
