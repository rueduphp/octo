<?php
namespace Octo;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class Elegant extends EloquentModel implements FastModelInterface
{
    protected $guarded  = [];
    public $__capsule;

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
     * @param array $columns
     * @return EloquentCollection|EloquentModel[]
     * @throws \ReflectionException
     */
    public static function all($columns = ['*'])
    {
        return gi()->make(get_called_class())->alls(is_array($columns) ? $columns : func_get_args());
    }

    /**
     * @param array|string $relations
     * @return \Illuminate\Database\Eloquent\Builder|EloquentModel
     * @throws \ReflectionException
     */
    public static function with($relations)
    {
        return gi()->make(get_called_class())->withRelation(
            is_string($relations) ? func_get_args() : $relations
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     * @throws \ReflectionException
     */
    public static function query()
    {
        return gi()->make(get_called_class())->q();
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
        return gi()->call(gi()->make(get_called_class()), $m, ...$a);
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

        if (fnmatch('get*', $m) && strlen($m) > 3) {
            $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($m, 3)));
            $key                = Strings::lower($uncamelizeMethod);

            return $this->{$key} ?? current($a);
        } elseif (fnmatch('set*', $m) && strlen($m) > 3) {
            $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($m, 3)));
            $key                = Strings::lower($uncamelizeMethod);

            $this->{$key} = current($a);

            return $this;
        }

        if (in_array($m, ['increment', 'decrement'])) {
            return $this->$m(...$a);
        }

        if ('q' === $m || 'builder' === $m) {
            return $this->newQuery();
        }

        if ('alls' === $m) {
            return new ElegantCollection($this->newQuery()->get(is_array(current($a)) ? current($a) : $a));
        }

        if ('withRelation' === $m) {
            return $this->newQuery()->with(
                is_string(current($a)) ? func_get_args() : current($a)
            );
        }

        if ('list' === $m) {
            $field = 2 === count($a) ? end($a) : $this->getKeyName();

            return Arrays::pluck(
                $this
                    ->newQuery()
                    ->select(current($a), $field)
                    ->get()
                    ->toArray(),
                current($a),
                $field
            );
        }

        if ('like' === $m) {
            return $this->newQuery()->where(current($a), 'like', end($a));
        }

        if ('orLike' === $m) {
            return $this->newQuery()->where(current($a), 'like', end($a), 'or');
        }

        if ('null' === $m) {
            return $this->newQuery()->whereNull(current($a));
        }

        if ('orNull' === $m) {
            return $this->newQuery()->whereNull(current($a), 'or');
        }

        if ('notNull' === $m) {
            return $this->newQuery()->whereNull(current($a), 'and', true);
        }

        if ('orNotNull' === $m) {
            return $this->newQuery()->whereNull(current($a), 'or', true);
        }

        if ('or' === $m) {
            $params = array_merge($a, ['or']);

            return $this->newQuery()->where(...$params);
        }

        $callable = [$this->newQuery(), $m];

        $params = array_merge($callable, $a);

        $result = gi()->call(...$params);

        if (is_object($result)) {
            if ($result instanceof EloquentCollection) {
                return new ElegantCollection($result);
            }
        }

        return $result;
    }

    /**
     * @param array $options
     * @return bool|Elegant
     */
    public function save(array $options = [])
    {
        $status = parent::save($options);

        return true === $status ? $this : $status;
    }

    /**
     * @return FastFactory
     * @throws \ReflectionException
     */
    public static function factory()
    {
        return new FastFactory($class = get_called_class(), gi()->make($class));
    }

    /**
     * @return FastFactory
     */
    public function factorer()
    {
        return new FastFactory(get_class($this), $this);
    }
}

class ElegantCollection
{
    use Macroable;

    /**
     * @var EloquentCollection
     */
    private $collection;

    public function __construct(EloquentCollection $collection)
    {
        $this->collection = $collection;
    }

    public function __call(string $method, array $parameters)
    {
        return $this->collection->{$method}($parameters);
    }
}
