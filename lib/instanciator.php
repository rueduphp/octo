<?php
namespace Octo;

use Closure;
use Exception as PHPException;
use Psr\Http\Message\ServerRequestInterface;

class Instanciator
{
    public function factory()
    {
        $args   = func_get_args();

        $class  = array_shift($args);

        return $this->make($class, $args, false);
    }

    public function foundry()
    {
        $args   = func_get_args();

        return $this->factory(...$args);
    }

    public function singleton()
    {
        $args   = func_get_args();

        $class  = array_shift($args);

        return $this->make($class, $args, true);
    }

    public function make($make, $args = [], $singleton = true)
    {
        if ($i = $this->autowire($make)) {
            $binds[$make] = $this->resolver($i);

            $this->binds($binds);

            return $i;
        }

        $binds = $this->binds();

        $args       = arrayable($args) ? $args->toArray() : $args;
        $callable   = isAke($binds, $make, null);

        if ($callable && is_callable($callable) && $singleton) {
            return $callable();
        }

        try {
            $ref = new Reflector($make);
        } catch (\Exception $e) {
            exception('Instanciator', $e->getMessage());
        }

        $canMakeInstance    = $ref->isInstantiable();

        if ($canMakeInstance) {
            $maker = $ref->getConstructor();

            if ($maker) {
                $params = $maker->getParameters();

                if (empty($args) || count($args) != count($params)) {
                    $instanceParams = [];

                    foreach ($params as $param) {
                        $p = null;

                        if (!empty($args)) {
                            $p = array_shift($args);

                            if (is_null($p)) {
                                try {
                                    $p = $param->getDefaultValue();
                                } catch (PHPException $e) {
                                    $p = null;
                                }
                            }
                        } else {
                            $classParam = $param->getClass();

                            if ($classParam) {
                                try {
                                    $p = $this->factory($classParam->getName());
                                } catch (\Exception $e) {
                                    exception('Instanciator', $e->getMessage());
                                }
                            } else {
                                try {
                                    $p = $param->getDefaultValue();
                                } catch (PHPException $e) {
                                    exception(
                                        'Instanciator',
                                        $param->getName() . " parameter has no default value."
                                    );
                                }
                            }
                        }

                        $instanceParams[] = $p;
                    }

                    if (!empty($instanceParams)) {
                        $i = $ref->newInstanceArgs($instanceParams);
                    } else {
                        $i = $ref->newInstance();
                    }
                } else {
                    $i = $ref->newInstanceArgs($args);
                }

                $binds[$make] = $this->resolver($i);

                $this->binds($binds);

                return $i;
            } else {
                $i = $ref->newInstance();

                $binds[$make] = $this->resolver($i);

                $this->binds($binds);

                return $i;
            }
        } else {
            exception('Instanciator', "The class $make is not intantiable.");
        }

        exception('Instanciator', "The class $make is not set.");
    }

    public function call()
    {
        $args       = func_get_args();
        $object     = array_shift($args);
        $method     = array_shift($args);
        $fnParams   = $args;
        $reflection = new Reflector(get_class($object));
        $ref        = $reflection->getMethod($method);
        $params     = $ref->getParameters();

        if (empty($args) || count($args) != count($params)) {
            foreach ($params as $param) {
                if (!empty($args)) {
                    $p = array_shift($args);

                    if (is_null($p)) {
                        try {
                            $p = $param->getDefaultValue();
                        } catch (PHPException $e) {
                            $p = null;
                        }
                    }
                } else {
                    $classParam = $param->getClass();

                    if ($classParam) {
                        $p = $this->factory($classParam->getName());
                    } else {
                        try {
                            $p = $param->getDefaultValue();
                        } catch (PHPException $e) {
                            if ($fnParams[0] instanceof ServerRequestInterface) {
                                $var    = $param->getName();
                                $p      = $fnParams[0]->getAttribute($var);
                            } else {
                                exception(
                                    'Instanciator',
                                    $param->getName() . " parameter has no default value."
                                );
                            }
                        }
                    }

                    $fnParams[] = $p;
                }
            }
        }

        $closure = $ref->getClosure($object);

        $args = array_merge([$closure], $fnParams);

        return $this->resolve(...$args);
    }

    protected function binds($concern = null)
    {
        $binds = Registry::get('core.all.binds', []);

        if (is_array($concern)) {
            Registry::set('core.all.binds', $concern);
            $this->registeredClasses($concern);
        } else {
            return $binds;
        }
    }

    /**
     * @return array
     */
    public function getBinds()
    {
        return $this->binds();
    }

    public function getWires(): array
    {
        return Registry::get('core.wires', []);
    }

    /**
     * @param array $classes
     */
    protected function registeredClasses(array $classes)
    {
        $data = Registry::get('core.Fastcontainer.registered', []);

        foreach ($classes as $class => $callback) {
            $data[$class] = true;
        }

        Registry::set('core.Fastcontainer.registered', $data);
    }

    /***
     * @param string $concern
     *
     * @param $callable
     */
    public function wire(string $concern, $callable)
    {
        if (!is_callable($callable)) {
            $callable = function () use ($callable) { return $callable; };
        }

        $wires = Registry::get('core.wires', []);

        $wires[$concern] = $callable;

        Registry::set('core.wires', $wires);
    }

    /**
     * @param string $file
     */
    public function wiring(string $file)
    {
        if (is_file($file)) {
            $wires = include $file;

            foreach ($wires as $concern => $callable) {
                $this->wire($concern, $callable);
            }
        }
    }

    /**
     * @param string $concern
     * @param bool $raw
     *
     * @return mixed
     */
    public function autowire(string $concern, bool $raw = false)
    {
        $wires      = Registry::get('core.wires', []);
        $callable   = isAke($wires, $concern, null);

        if (!$raw && $callable && is_callable($callable)) {
            return $callable();
        }

        return $callable;
    }

    /**
     * @param $object
     *
     * @return Closure
     */
    public function resolver($object)
    {
        if (is_callable($object)) {
            $object = $this->lazy($object);
        }

        if (is_string($object)) {
            $cb = function () use ($object) {
                return $this->make($object);
            };

            $object = $this->lazy($cb);
        }

        return function () use ($object) {
            if (is_callable($object)) {
                return $object();
            }

            return $object;
        };
    }

    /**
     * @param mixed $callable
     *
     * @return Lazy
     */
    public function lazy($callable)
    {
        $args = func_get_args();
        array_shift($args);

        return new Lazy($callable, $args);
    }

    /**
     * @return mixed|null
     */
    public function with()
    {
        return with(...func_get_args());
    }

    /**
     * @return mixed|null
     */
    public function resolve()
    {
        $args       = func_get_args();
        $callable   = array_shift($args);

        if ($callable instanceof Closure) {
            return with(...func_get_args());
        }

        if (is_array($callable) && is_callable($callable)) {
            $params = array_merge($callable, $args);

            return $this->call(...$params);
        }

        return null;
    }
}
