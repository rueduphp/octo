<?php
namespace Octo;

use Closure;

class Dynamicentity
{
    /** @var string */
    protected $entity;

    /** @var string  */
    protected $cache = Caching::class;

    /** @var null|callable */
    protected $iterator = null;

    /** @var array  */
    protected static $booted = [];

    /**
     * @throws \ReflectionException
     */
    public function __construct()
    {
        $class  = get_called_class();

        $methods = get_class_methods($this);

        if (!isset(static::$booted[$class])) {
            static::$booted[$class] = true;

            if (in_array('boot', $methods)) {
                gi()->call($this, 'boot');
            }

            if (in_array('events', $methods)) {
                gi()->call($this, 'events');
            }

            if (in_array('policies', $methods)) {
                gi()->call($this, 'policies');
            }

            $this->fire('booting');

            $traits = allClasses($class);

            if (!empty($traits)) {
                foreach ($traits as $trait) {
                    $tab        = explode('\\', $trait);
                    $traitName  = Strings::lower(end($tab));
                    $method     = lcfirst(Strings::camelize('boot_' . $traitName . '_trait'));

                    if (in_array($method, $methods)) {
                        gi()->call($this, $method);
                    }

                    $method = lcfirst(Strings::camelize('boot_' . $traitName));

                    if (in_array($method, $methods)) {
                        forward_static_call([$class, $method]);
                    }
                }
            }

            $this->fire('booted');
        }

        addDynamicEntity($this);
    }

    /**
     * @throws \ReflectionException
     */
    public static function init()
    {
        addDynamicEntity(static::called());
    }

    /**
     * @return Dynamicentity
     * @throws \ReflectionException
     */
    public static function called(): Dynamicentity
    {
        return gi()->make(get_called_class());
    }

    /**
     * @param array $data
     * @return Dynamicrecord
     * @throws \ReflectionException
     */
    public static function model($data = []): Dynamicrecord
    {
        return new Dynamicrecord($data, static::db(), static::called());
    }

    /**
     * @return Dynamicmodel
     * @throws \ReflectionException
     */
    protected static function db(): Dynamicmodel
    {
        /** @var Dynamicentity $self */
        $self   = gi()->make(get_called_class());
        $cache  = gi()->make($self->cache, ['eav.' . $self->entity]);

        return gi()->make(Dynamicmodel::class, [$self->entity, $cache], false);
    }

    /**
     * @return string
     */
    public function entity(): string
    {
        return $this->entity;
    }

    /**
     * @return Iterator
     * @throws \ReflectionException
     */
    public static function get(): Iterator
    {
        /** @var Dynamicentity $self */
        $self = static::called();
        $db   = static::db();

        if ($self->iterator instanceof Closure) {
            return $db->each($self->iterator);
        }

        return $db->model($self);
    }

    /**
     * @param $data
     * @return Dynamicrecord
     * @throws \ReflectionException
     */
    public static function create($data)
    {
        return static::model($data)->save();
    }

    /**
     * @param string $class
     * @return Dynamicentity
     * @throws \ReflectionException
     */
    public static function observe(string $class): Dynamicentity
    {
        $observers = getCore('dyn.observers', []);
        $self = get_called_class();
        $observers[$self] = gi()->make($class);
        setCore('dyn.observers', $observers);

        return gi()->factory($self);
    }

    public static function clearBooted()
    {
        static::$booted = [];
    }

    /**
     * @param string $event
     * @param null $concern
     * @param bool $return
     *
     * @return mixed|null
     *
     * @throws \ReflectionException
     */
    public function fire(string $event, $concern = null, bool $return = false)
    {
        $methods = get_class_methods($this);
        $method  = 'on' . Strings::camelize($event);

        if (in_array($method, $methods)) {
            $result = $this->{$method}($concern);

            if (true === $return) {
                return $result;
            }
        } else {
            $observers = getCore('dyn.observers', []);
            $self = get_called_class();

            $observer = isAke($observers, $self, null);

            if (null !== $observer) {
                $methods = get_class_methods($observer);

                if (in_array($event, $methods)) {
                    $result = gi()->call($observer, $event, $concern);

                    if (true === $return) {
                        return $result;
                    }
                }
            }
        }

        return $concern;
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws \ReflectionException
     */
    public static function __callStatic($name, $arguments)
    {
        return static::db()->{$name}(...$arguments);
    }

    /**
     * @param callable|null $iterator
     * @return Dynamicentity
     */
    public function setIterator(?callable $iterator): Dynamicentity
    {
        $this->iterator = $iterator;

        return $this;
    }

    /**
     * @return array
     */
    public function guarded()
    {
        return [];
    }
}