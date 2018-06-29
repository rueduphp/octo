<?php
namespace Octo;

class Custom
{
    /**
     * @var object
     */
    private $__target;

    /**
     * @param mixed ...$args
     * @throws \ReflectionException
     */
    public function __construct(...$args)
    {
        $target = array_shift($args);

        if (is_string($target) && class_exists($target)) {
            $target = gi()->make($target, $args);
        }

        set('custom.instance', $this);

        $this->__target = $target;
    }

    /**
     * @param mixed ...$parameters
     * @return Custom
     * @throws \ReflectionException
     */
    public function override(...$parameters): self
    {
        $container  = getContainer();
        $method     = array_shift($parameters);
        $callable   = get_class($this->__target) . '@' . $method;
        $args       = array_merge([$callable], $parameters);

        $container::hook(...$args);

        return $this;
    }

    /**
     * @param mixed ...$parameters
     * @return mixed
     * @throws \ReflectionException
     */
    public function withNative(...$parameters)
    {
        $container  = getContainer();
        $method     = array_shift($parameters);
        $params     = array_merge([$this->__target, $method], $parameters);
        $res        = gi()->call(...$params);
        $callable   = get_class($this->__target) . '@' . $method;
        $args       = array_merge([$callable], $parameters);
        $args       = array_merge($args, [$res]);

        return $container::callHook(...$args);
    }

    /**
     * @param string $method
     * @param array $parameters
     * @return mixed
     * @throws FastContainerException
     * @throws \ReflectionException
     */
    public static function __callStatic(string $method, array $parameters)
    {
        return get('custom.instance')->{$method}(...$parameters);
    }

    /**
     * @param string $method
     * @param array $parameters
     *
     * @return mixed
     *
     * @throws \ReflectionException
     */
    public function __call(string $method, array $parameters)
    {
        $container  = getContainer();
        $callable   = get_class($this->__target) . '@' . $method;
        $args       = array_merge([$callable], $parameters);

        return $container::callHook(...$args);
    }
}
