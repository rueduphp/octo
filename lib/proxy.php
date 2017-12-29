<?php
namespace Octo;

use BadMethodCallException;
use Closure;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class Proxy
{
    /**
     * @var string
     */
    protected $id;

    public function __construct($concern)
    {
        $this->id = get_class($concern) . '_' . token();

        $macros = Registry::get('proxy.macros', []);

        $ref = new ReflectionClass($concern);

        $properties = $ref->getProperties(
            ReflectionProperty::IS_PRIVATE |
            ReflectionProperty::IS_PUBLIC |
            ReflectionProperty::IS_PROTECTED
        );

        foreach ($properties as $property) {
            $property->setAccessible(true);
            $this->{$property->name} = $property->getValue($concern);
        }

        $computed = [];

        $methods = $ref->getMethods(
            ReflectionMethod::IS_PRIVATE |
            ReflectionMethod::IS_PUBLIC |
            ReflectionMethod::IS_PROTECTED
        );

        foreach ($methods as $method) {
            if ('__construct' === $method->name) {
                continue;
            }

            $method->setAccessible(true);

            $computed[$method->name] = function () use ($concern, $method) {
                $fn = $method->name;
                $params = array_merge([$concern, $fn], func_get_args());

                return instanciator()->call(...$params);
            };
        }

        $macros[$this->id] = $computed;

        Registry::set('proxy.macros', $macros);
    }

    /**
     * @param string $method
     * @param array $parameters
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        $macros = aget(Registry::get('proxy.macros', []), $this->id, []);

        $macro = aget($macros, $method, false);

        if (false === $macro) {
            throw new BadMethodCallException("Method {$method} does not exist.");
        }

        if ($macro instanceof Closure) {
            return call_user_func_array($macro->bindTo($this, static::class), $parameters);
        }

        return $macro(...$parameters);
    }
}