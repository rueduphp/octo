<?php
namespace Octo;

use Closure;
use Exception as PHPException;
use ReflectionFunction;

class Instanciator
{
    use Eventable;

    protected $cache;

    /**
     * @return Listener
     * @throws \ReflectionException
     */
    public function resolving(...$args)
    {
        if (1 === count($args)) {
            $event = 'all.resolving';

            return $this->on($event, current($args));
        } elseif (2 === count($args)) {
            $event = current($args) . '.resolving';

            return $this->on($event, end($args));
        }
    }

    /**
     * @return mixed|object
     *
     * @throws \ReflectionException
     */
    public function factory(...$args)
    {
        $class  = array_shift($args);

        return is_string($class) ? $this->make($class, $args, false) : $this->makeClosure($class, $args);
    }

    /**
     * @return mixed|object
     *
     * @throws \ReflectionException
     */
    public function foundry(...$args)
    {
        return $this->factory(...$args);
    }

    /**
     * @return mixed|object
     *
     * @throws \ReflectionException
     */
    public function singleton(...$args)
    {
        $class  = array_shift($args);

        return is_string($class) ? $this->make($class, $args, true) : $this->makeClosure($class, $args);
    }

    /**
     * @param string $make
     * @param array $args
     * @param bool $singleton
     *
     * @return mixed|object
     */
    public function make(string $make, array $args = [], bool $singleton = true)
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

        $canMakeInstance = $ref->isInstantiable();

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

                            $classParam = $param->getClass();

                            if ($classParam) {
                                $c = $classParam->getName();
                                $made = false;

                                if (!$p instanceof $c) {
                                    array_unshift($args, $p);

                                    try {
                                        $p = $this->factory($c);

                                        if ($p instanceof FastModelInterface) {
                                            $p = $this->makeModel($p);
                                            $made = true;
                                        }
                                    } catch (\Exception $e) {
                                        exception('Instanciator', $e->getMessage());
                                    }
                                }

                                if (false === $made) {
                                    if ($param->hasType()) {
                                        $t = (string) $param->getType()->getName();

                                        if (is_object($p) && !$p instanceof $t) {
                                            if (true === $param->isDefaultValueAvailable()) {
                                                $p = $param->getDefaultValue();
                                            }
                                        }
                                    }
                                }
                            } else {
                                if ($param->hasType()) {
                                    if ($param->getType()->getName() !== (string) gettype($p)) {
                                        if (!empty($args)) {
                                            $found = false;

                                            foreach ($args as $k => $a) {
                                                if (!$found && $param->getType()->getName() === (string) gettype($a)) {
                                                    $args[$k] = $p;
                                                    $p = $a;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        } else {
                            $classParam = $param->getClass();

                            if ($classParam) {
                                try {
                                    $p = $this->factory($classParam->getName());

                                    if ($p instanceof FastModelInterface) {
                                        $p = $this->makeModel($p);
                                    }
                                } catch (\Exception $e) {
                                    exception('Instanciator', $e->getMessage());
                                }
                            } else {
                                try {
                                    $p = $param->getDefaultValue();
                                } catch (PHPException $e) {
                                    $attr = getContainer()->getRequest()->getAttribute($param->getName());

                                    if ($attr) {
                                        $p = $attr;
                                    } else {
                                        exception(
                                            'Instanciator',
                                            $param->getName() . " parameter has no default value."
                                        );
                                    }
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

    /**
     * @return mixed
     *
     * @throws \ReflectionException
     */
    public function invoker(...$args)
    {
        return $this->makeClosure(...$args);
    }

    /**
     * @param array ...$args
     *
     * @return mixed
     *
     * @throws \ReflectionException
     */
    public function callMethod(...$args)
    {
        return $this->makeClosure(...$args);
    }

    /**
     * @param Octal $entity
     * @return mixed
     * @throws \ReflectionException
     */
    private function makeModel($entity)
    {
        $key = 'id';

        if ($entity instanceof \Illuminate\Database\Eloquent\Model) {
            $key = $entity->getKeyName();
        } elseif ($entity instanceof Entity) {
            $key = $entity->pk();
        }

        $id = getContainer()->getRequest()->getAttribute($key);

        if (!is_null($id)) {
            return $entity->find($id);
        }

        return $entity;
    }

    /**
     * @return mixed
     *
     * @throws \ReflectionException
     */
    public function makeClosure(...$args)
    {
        $closure    = array_shift($args);

        $ref        = new ReflectionFunction($closure);
        $params     = $ref->getParameters();

        if (empty($args) || count($args) !== count($params)) {
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

                    $classParam = $param->getClass();

                    if ($classParam) {
                        $c = $classParam->getName();
                        $made = false;

                        if (!$p instanceof $c) {
                            array_unshift($args, $p);

                            try {
                                $p = $this->factory($c);

                                if ($p instanceof FastModelInterface) {
                                    $p = $this->makeModel($p);
                                    $made = true;
                                }
                            } catch (\Exception $e) {
                                exception('Instanciator', $e->getMessage());
                            }
                        }

                        if (false === $made) {
                            if ($param->hasType()) {
                                $t = (string) $param->getType()->getName();

                                if (is_object($p) && !$p instanceof $t) {
                                    if (true === $param->isDefaultValueAvailable()) {
                                        $p = $param->getDefaultValue();
                                    }
                                }
                            }
                        }
                    } else {
                        if ($param->hasType()) {
                            if ($param->getType()->getName() !== (string) gettype($p)) {
                                if (!empty($args)) {
                                    $found = false;

                                    foreach ($args as $k => $a) {
                                        if (!$found && $param->getType()->getName() === (string) gettype($a)) {
                                            $args[$k] = $p;
                                            $p = $a;
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else {
                    $classParam = $param->getClass();

                    if ($classParam) {
                        try {
                            $p = $this->factory($classParam->getName());

                            if ($p instanceof FastModelInterface) {
                                $p = $this->makeModel($p);
                            }
                        } catch (\Exception $e) {
                            exception('Instanciator', $e->getMessage());
                        }
                    } else {
                        try {
                            $p = $param->getDefaultValue();
                        } catch (PHPException $e) {
                            $attr = getContainer()->getRequest()->getAttribute($param->getName());

                            if ($attr) {
                                $p = $attr;
                            } else {
                                exception(
                                    'Instanciator',
                                    $param->getName() . " parameter has no default value."
                                );
                            }
                        }
                    }
                }

                $instanceParams[] = $p;
            }

            if (!empty($instanceParams)) {
                return $closure(...$instanceParams);
            } else {
                return $closure();
            }
        } else {
            return $closure(...$args);
        }
    }

    /**
     * @return mixed|null
     *
     * @throws \ReflectionException
     */
    public function interact(...$args)
    {
        $class  = array_shift($args);
        $params = array_merge([$this->factory($class)], $args);

        return $this->call(...$params);
    }

    /**
     * @return mixed|null
     *
     * @throws \ReflectionException
     */
    public function call(...$args)
    {
        $object     = array_shift($args);
        $method     = array_shift($args);
        $fnParams   = $args;
        $reflection = new Reflector(get_class($object));

        try {
            $ref    = $reflection->getMethod($method);
            $params = $ref->getParameters();

            if (empty($args) || count($args) !== count($params)) {
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

                        $classParam = $param->getClass();

                        if ($classParam) {
                            $c = $classParam->getName();
                            $made = false;

                            if (!$p instanceof $c) {
                                array_unshift($args, $p);

                                try {
                                    $p = $this->factory($c);

                                    if ($p instanceof FastModelInterface) {
                                        $p = $this->makeModel($p);
                                        $made = true;
                                    }
                                } catch (\Exception $e) {
                                    exception('Instanciator', $e->getMessage());
                                }
                            }

                            if (false === $made) {
                                if ($param->hasType()) {
                                    $t = (string) $param->getType()->getName();

                                    if (is_object($p) && !$p instanceof $t) {
                                        if (true === $param->isDefaultValueAvailable()) {
                                            $p = $param->getDefaultValue();
                                        }
                                    }
                                }
                            }
                        } else {
                            if ($param->hasType()) {
                                if ($param->getType()->getName() !== (string) gettype($p)) {
                                    if (!empty($args)) {
                                        $found = false;

                                        foreach ($args as $k => $a) {
                                            if (!$found && $param->getType()->getName() === (string) gettype($a)) {
                                                $args[$k] = $p;
                                                $p = $a;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        $classParam = $param->getClass();

                        if ($classParam) {
                            $p = $this->factory($classParam->getName());

                            if ($p instanceof FastModelInterface) {
                                $p = $this->makeModel($p);
                            }
                        } else {
                            try {
                                $p = $param->getDefaultValue();
                            } catch (PHPException $e) {
                                $attr = getContainer()->getRequest()->getAttribute($param->getName());

                                if ($attr) {
                                    $p = $attr;
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
        } catch (\ReflectionException $e) {
            return $object->{$method}(...$args);
        }
    }

    /**
     * @param null $concern
     *
     * @return mixed
     */
    public function binds($concern = null)
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

    /**
     * @return array
     */
    public function getWires(): array
    {
        return Registry::get('core.wires', []);
    }

    /**
     * @param array $classes
     */
    protected function registeredClasses(array $classes): void
    {
        $data = Registry::get('core.Fastcontainer.registered', []);

        foreach ($classes as $class => $callback) {
            $data[$class] = true;
        }

        Registry::set('core.Fastcontainer.registered', $data);
    }

    /***
     * @param string $concern
     * @param mixed $callable
     */
    public function wire(string $concern, $callable): void
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
    public function wiring(string $file): void
    {
        if (is_file($file)) {
            $wires = include $file;

            foreach ($wires as $concern => $callable) {
                $this->wire($concern, $callable);
            }
        }
    }

    /**
     * @param array ...$args
     *
     * @return Instanciator
     */
    public function set(...$args): self
    {
        return $this->share(...$args);
    }

    /**
     * @param callable $callable
     * @param bool $return
     * @return mixed|null|Instanciator
     * @throws \ReflectionException
     */
    public function factor(callable $callable, bool $return = false)
    {
        if ($callable instanceof Closure) {
            $result = $this->makeClosure($callable);
        } elseif (is_array($callable)) {
            $result = $this->call($callable);
        } else {
            $result = $this->call($callable, '__invoke');
        }

        return $return ? $result : $this->set($result);
    }

    /**
     * @param array ...$factories
     * @return Instanciator
     * @throws \ReflectionException
     */
    public function factories(...$factories): self
    {
        foreach ($factories as $factory) {
            $this->factor($factory);
        }

        return $this;
    }

    /**
     * @param callable $callable
     * @param bool $return
     * @return mixed|null|Instanciator
     * @throws \ReflectionException
     */
    public function newFactor(callable $callable, bool $return = false)
    {
        $new = $this->factor($callable, true);
        $old = $this->get($class = get_class($new));

        setCore('old.factors.' . $class, $old);

        return $return ? $new : $this->set($new);
    }

    /**
     * @param string $class
     * @return $this|Instanciator
     */
    public function previousFactor(string $class)
    {
        $old = getCore('old.factors.' . $class);

        if (null !== $old) {
            delCore('old.factors.' . $class);

            return $this->set($old);
        }

        return $this;
    }

    /**
     * @param array ...$args
     *
     * @return Instanciator
     *
     * @throws \ReflectionException
     */
    public function __invoke(...$args): self
    {
        return $this->share(...$args);
    }

    /**
     * @param string $class
     * @return mixed|object
     * @throws \ReflectionException
     */
    public function getOr(string $class)
    {
        if (!$this->has($class)) {
            return $this->set($class)->get($class);
        }

        return $this->get($class);
    }

    /**
     * @param array ...$args
     * @return bool
     * @throws \ReflectionException
     */
    public function has(...$args): bool
    {
        return null !== $this->get(...$args);
    }

    /**
     * @param $concern
     */
    public function del($concern)
    {
        $wires = $this->getWires();

        if (is_object($concern)) {
            $concern = get_class($concern);
        }

        unset($wires[$concern]);

        Registry::set('core.wires', $wires);
    }

    /**
     * @param array ...$args
     */
    public function delete(...$args)
    {
        $this->del(...$args);
    }

    /**
     * @param array ...$args
     */
    public function remove(...$args)
    {
        $this->del(...$args);
    }

    /**
     * @param $concern
     *
     * @return Instanciator
     */
    public function share($concern): self
    {
        if (is_object($concern)) {
            $class = get_class($concern);
            $this->wire($class, $concern);
        } elseif (is_string($concern)) {
            $args   = func_get_args();
            $key    = array_shift($args);

            if (empty($args) && class_exists($key)) {
                $args[] = $this->make($key);
            }

            $value  = array_shift($args);

            $this->wire($key, $value);
        }

        return $this;
    }

    /**
     * @param string $concern
     *
     * @return mixed
     */
    public function shared(string $concern)
    {
        return $this->autowire($concern);
    }

    /**
     * @param string $concern
     *
     * @return mixed|object
     *
     * @throws \ReflectionException
     */
    public function get(string $concern)
    {
        $aliases = get('instanciator.aliases', []);
        $alias = isAke($aliases, $concern, null);

        if (is_string($alias) && class_exists($alias)) {
            $params = func_get_args();
            array_shift($params);

            $closure = function () use ($alias, $params) {
                return $this->make($alias, $params, true);
            };

            $aliases[$concern] = $closure;
            set('instanciator.aliases', $aliases);

            return $closure();
        } elseif (is_callable($alias)) {
            return $alias();
        }

        return $this->autowire($concern);
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

        if (!$raw && is_callable($callable)) {
            return $callable();
        }

        return $callable;
    }

    /**
     * @param array ...$args
     *
     * @return Closure
     */
    public function callable(...$args): Closure
    {
        $class = array_shift($args);

        return function () use ($class, $args) {
            return $this->make($class, $args, false);
        };
    }

    /**
     * @param array ...$args
     * @return mixed|object|Instanciator
     * @throws \ReflectionException
     */
    public function invokable(...$args)
    {
        $class = array_shift($args);

        if ($this->has($class)) {
            return $this->get(...$args);
        }

        return $this->set($class, $this->callable(...$args));
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
    public function with(...$args)
    {
        return with(...$args);
    }

    /**
     * @return mixed|null
     *
     * @throws \ReflectionException
     */
    public function resolve(...$args)
    {
        $callable   = array_shift($args);

        if ($callable instanceof Closure) {
            return $this->makeClosure(...func_get_args());
        }

        if (is_array($callable) && is_callable($callable)) {
            $params = array_merge($callable, $args);

            return $this->call(...$params);
        }

        return null;
    }

    /**
     * @param string $key
     * @param callable|null $resolver
     *
     * @return mixed|null
     *
     * @throws Exception
     * @throws PHPException
     */
    public function cache(string $key, ?callable $resolver = null)
    {
        $cache  = $this->getCache();
        $args   = func_get_args();
        $class  = $key = array_shift($args);

        if ($cache) {
            $key = 'di.' . $key;

            if ($cache->has($key)) {
                return $this->get($key);
            } else {
                $resolver = array_shift($args);

                if (!is_callable($resolver)) {
                    $resolver = function () use ($class) {
                        return $this->singleton($class);
                    };
                }

                $resolved = $resolver(...$args);

                $cache->set($key, $resolved, strtotime('+24 hour') - time());

                return $resolved;
            }
        }

        return null;
    }

    /**
     * @param mixed $cache
     *
     * @return Instanciator
     */
    public function setCache($cache): self
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * @return Cache
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * @param string $name
     * @param string $class
     *
     * @return Instanciator
     *
     * @throws \ReflectionException
     */
    public function alias(string $name, string $class): self
    {
        $aliases = get('instanciator.aliases', []);
        $aliases[$name] = $class;
        set('instanciator.aliases', $aliases);

        return $this;
    }
}
