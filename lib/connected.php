<?php
namespace Octo;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use PDO;

class Connected extends EloquentModel implements FastModelInterface
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
            $this->__capsule = Connector::model($this, $this->getPdo());
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
     */
    public static function factory()
    {
        $class = get_called_class();

        return new FastFactory($class, new $class());
    }

    public function getPdo(): PDO
    {
        throw new RuntimeException(get_called_class() . ' does not implement PDO.');
    }

    /**
     * @return \Illuminate\Database\Schema\Builder
     * @throws \ReflectionException
     */
    public function schema()
    {
        /** @var Connected $self */
        $self = gi()->make(get_called_class());

        return Connector::schema($self->getPdo());
    }
}
