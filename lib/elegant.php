<?php
namespace Octo;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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

        if ('list' === $m) {
            return $this->get()->pluck(...$a);
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
}

class ElegantCollection
{
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
