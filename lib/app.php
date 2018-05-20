<?php
    namespace Octo;

    use ArrayAccess as AA;
    use Closure;
    use GuzzleHttp\Psr7\Response as Psr7Response;
    use Psr\Http\Message\ServerRequestInterface;
    use Zend\Expressive\Router\FastRouteRouter;

    class App implements AA, AppInterface
    {
        use Macroable;

        protected static $instance;
        protected $resolved = [];
        protected $bindings = [];
        protected $instances = [];
        protected $aliases = [];
        protected $children = [];
        protected $tags = [];
        protected $buildStack = [];
        public $contextual = [];
        protected $reboundCallbacks = [];
        protected $globalResolvingCallbacks = [];
        protected $globalAfterResolvingCallbacks = [];
        protected $resolvingCallbacks = [];
        protected $afterResolvingCallbacks = [];

        /**
         * @param null $concern
         *
         * @return ServerRequestInterface
         */
        public static function request($concern = null)
        {
            if ($concern instanceof ServerRequestInterface) {
                getContainer()->setRequest($concern);
            }

            return getContainer()->getRequest();
        }

        /**
         * @return Psr7Response
         */
        public static function response()
        {
            return getContainer()->getResponse();
        }

        /**
         * @return FastRouteRouter
         */
        public static function router()
        {
            return getContainer()->router();
        }

        /**
         * @param null $concern
         *
         * @return Session
         *
         * @throws \TypeError
         */
        public static function session($concern = null)
        {
            if ($concern) {
                getContainer()->setSession($concern);
            }

            return getContainer()->getSession();
        }

        /**
         * @param null|string $concern
         *
         * @return string
         *
         * @throws \TypeError
         */
        public static function lang(?string $concern = null)
        {
            if ($concern) {
                getContainer()->setLang($concern);
            }

            return getContainer()->getLang();
        }

        /**
         * @param null $concern
         *
         * @return FastPhpRenderer|FastTwigRenderer
         */
        public static function renderer($concern = null)
        {
            if ($concern) {
                getContainer()->setRenderer($concern);
            }

            return getContainer()->getRenderer();
        }

        public function when($real)
        {
            return instanciator()->factory(Contextual::class, $this, $real);
        }

        /**
         * @param array $config
         * @return Fast
         * @throws \ReflectionException
         */
        public static function create(array $config = [])
        {
            return gi()->make(Fast::class, [$config]);
        }

        protected function resolvable($toResolve)
        {
            return $this->bound($toResolve);
        }

        public function bound($toResolve)
        {
            return isset($this->bindings[$toResolve])
                || isset($this->instances[$toResolve])
                || $this->isAlias($toResolve)
            ;
        }

        public function resolved($toResolve)
        {
            return isset($this->resolved[$toResolve]) || isset($this->instances[$toResolve]);
        }

        public function isAlias($name)
        {
            return isset($this->aliases[$name]);
        }

        public function bind($toResolve, $real = null, $shared = false)
        {
            if (is_array($toResolve)) {
                list($toResolve, $alias) = $this->extractAlias($toResolve);

                $this->alias($toResolve, $alias);
            }

            $this->dropStaleInstances($toResolve);

            if (is_null($real)) {
                $real = $toResolve;
            }

            if (!$real instanceof Closure) {
                $real = $this->getClosure($toResolve, $real);
            }

            $this->bindings[$toResolve] = compact('concrete', 'shared');

            if ($this->resolved($toResolve)) {
                $this->rebound($toResolve);
            }

            return $this;
        }

        protected function getClosure($toResolve, $real)
        {
            return function($c, $args = []) use ($toResolve, $real) {
                $method = ($toResolve == $real) ? 'build' : 'make';

                return $c->$method($real, $args);
            };
        }

        public function addContextualBinding($real, $toResolve, $implementation)
        {
            $this->contextual[$real][$toResolve] = $implementation;
        }

        public function bindIf($toResolve, $real = null, $shared = false)
        {
            if (!$this->bound($toResolve)) {
                $this->bind($toResolve, $real, $shared);
            }
        }

        public function singleton($toResolve, $real = null)
        {
            $this->bind($toResolve, $real, true);
        }

        public function share(Closure $closure)
        {
            return function($container) use ($closure) {
                static $object;

                if (is_null($object)) {
                    $object = $closure($container);
                }

                return $object;
            };
        }

        public function bindShared($toResolve, Closure $closure)
        {
            $this->bind($toResolve, $this->share($closure), true);
        }

        public function extend($toResolve, Closure $closure)
        {
            if (isset($this->instances[$toResolve])) {
                $this->instances[$toResolve] = $closure($this->instances[$toResolve], $this);

                $this->rebound($toResolve);
            } else {
                $this->extenders[$toResolve][] = $closure;
            }
        }

        public function instance($toResolve, $instance)
        {
            if (is_array($toResolve)) {
                list($toResolve, $alias) = $this->extractAlias($toResolve);

                $this->alias($toResolve, $alias);
            }

            unset($this->aliases[$toResolve]);

            $bound = $this->bound($toResolve);

            $this->instances[$toResolve] = $instance;

            if ($bound) {
                $this->rebound($toResolve);
            }
        }

        public function tag($toResolves, $tags)
        {
            $tags = is_array($tags) ? $tags : array_slice(func_get_args(), 1);

            foreach ($tags as $tag) {
                if (!isset($this->tags[$tag])) {
                    $this->tags[$tag] = [];
                }

                foreach ((array) $toResolves as $toResolve) {
                    $this->tags[$tag][] = $toResolve;
                }
            }
        }

        public function tagged($tag)
        {
            $results = [];

            foreach ($this->tags[$tag] as $toResolve) {
                $results[] = $this->make($toResolve);
            }

            return $results;
        }

        public function alias($toResolve, $alias)
        {
            $this->aliases[$alias] = $toResolve;
        }

        protected function extractAlias(array $definition)
        {
            return [key($definition), current($definition)];
        }

        public function rebinding($toResolve, Closure $callback)
        {
            $this->reboundCallbacks[$toResolve][] = $callback;

            if ($this->bound($toResolve)) return $this->make($toResolve);
        }

        public function refresh($toResolve, $target, $method)
        {
            return $this->rebinding($toResolve, function($app, $instance) use ($target, $method) {
                $target->{$method}($instance);
            });
        }

        protected function rebound($toResolve)
        {
            $instance = $this->make($toResolve);

            foreach ($this->getReboundCallbacks($toResolve) as $callback) {
                call_user_func($callback, $this, $instance);
            }
        }

        protected function getReboundCallbacks($toResolve)
        {
            if (isset($this->reboundCallbacks[$toResolve])) {
                return $this->reboundCallbacks[$toResolve];
            }

            return [];
        }

        public function wrap(Closure $callback, array $args = [])
        {
            return function() use ($callback, $args) {
                return $this->call($callback, $args);
            };
        }

        public function call($callback, array $args = [], $defaultMethod = null)
        {
            if ($this->isCallableWithAtSign($callback) || $defaultMethod) {
                return $this->callClass($callback, $args, $defaultMethod);
            }

            $dependencies = $this->getMethodDependencies($callback, $args);

            return call_user_func_array($callback, $dependencies);
        }

        protected function isCallableWithAtSign($callback) {
            if (!is_string($callback)) return false;

            return strpos($callback, '@') !== false;
        }

        protected function getMethodDependencies($callback, $args = [])
        {
            $dependencies = [];

            foreach ($this->getCallReflector($callback)->getParameters() as $key => $parameter) {
                $this->addDependencyForCallParameter($parameter, $args, $dependencies);
            }

            return array_merge($dependencies, $args);
        }

        protected function getCallReflector($callback)
        {
            if (is_string($callback) && strpos($callback, '::') !== false) {
                $callback = explode('::', $callback);
            }

            if (is_array($callback)) {
                return new \ReflectionMethod($callback[0], $callback[1]);
            }

            return new \ReflectionFunction($callback);
        }

        protected function addDependencyForCallParameter(\ReflectionParameter $parameter, array &$args, &$dependencies)
        {
            if (array_key_exists($parameter->name, $args)) {
                $dependencies[] = $args[$parameter->name];

                unset($args[$parameter->name]);
            } elseif ($parameter->getClass()) {
                $dependencies[] = $this->make($parameter->getClass()->name);
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            }
        }

        protected function callClass($target, array $args = [], $defaultMethod = null)
        {
            $segments = explode('@', $target);

            $method = count($segments) == 2 ? $segments[1] : $defaultMethod;

            if (is_null($method)) {
                throw new \InvalidArgumentException("Method not provided.");
            }

            return $this->call([$this->make($segments[0]), $method], $args);
        }

        public function make($toResolve, $args = [])
        {
            $toResolve = $this->getAlias($toResolve);

            if (isset($this->instances[$toResolve])) {
                return $this->instances[$toResolve];
            }

            $real = $this->getReal($toResolve);

            if ($this->isBuildable($real, $toResolve)) {
                $object = $this->build($real, $args);
            } else {
                $object = $this->make($real, $args);
            }

            foreach ($this->getExtenders($toResolve) as $extender) {
                $object = $extender($object, $this);
            }

            if ($this->isShared($toResolve)) {
                $this->instances[$toResolve] = $object;
            }

            $this->fireResolvingCallbacks($toResolve, $object);

            $this->resolved[$toResolve] = true;

            return $object;
        }

        protected function getReal($toResolve)
        {
            if (!is_null($real = $this->getContextualConcrete($toResolve))) {
                return $real;
            }

            if (!isset($this->bindings[$toResolve])) {
                if ($this->missingLeadingSlash($toResolve) && isset($this->bindings['\\' . $toResolve])) {
                    $toResolve = '\\' . $toResolve;
                }

                return $toResolve;
            }

            return $this->bindings[$toResolve]['concrete'];
        }

        protected function getContextualConcrete($toResolve)
        {
            if (isset($this->contextual[end($this->buildStack)][$toResolve])) {
                return $this->contextual[end($this->buildStack)][$toResolve];
            }
        }

        protected function missingLeadingSlash($toResolve)
        {
            return is_string($toResolve) && strpos($toResolve, '\\') !== 0;
        }

        protected function getExtenders($toResolve)
        {
            if (isset($this->extenders[$toResolve])) {
                return $this->extenders[$toResolve];
            }

            return [];
        }

        public function build($real, $args = [])
        {
            if ($real instanceof Closure) {
                return $real($this, $args);
            }

            if (!class_exists($real)) {
                $real = '\\' . __namespace__ . '\\' . $real;
            }

            $reflector = new \ReflectionClass($real);

            if (!$reflector->isInstantiable()) {
                $message = "Target [$real] is not instantiable.";

                throw new \Exception($message);
            }

            $this->buildStack[] = $real;

            $constructor = $reflector->getConstructor();

            if (is_null($constructor)) {
                array_pop($this->buildStack);

                return new $real;
            }

            $dependencies = $constructor->getParameters();

            $args = $this->keyParametersByArgument(
                $dependencies,
                $args
            );

            $instances = $this->getDependencies(
                $dependencies,
                $args
            );

            array_pop($this->buildStack);

            return $reflector->newInstanceArgs($instances);
        }

        protected function getDependencies($args, array $primitives = [])
        {
            $dependencies = [];

            foreach ($args as $parameter) {
                $dependency = $parameter->getClass();

                if (array_key_exists($parameter->name, $primitives))  {
                    $dependencies[] = $primitives[$parameter->name];
                } elseif (is_null($dependency))  {
                    $dependencies[] = $this->resolveNonClass($parameter);
                } else  {
                    $dependencies[] = $this->resolveClass($parameter);
                }
            }

            return (array) $dependencies;
        }

        protected function resolveNonClass(\ReflectionParameter $parameter)
        {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            $message = "Unresolvable dependency resolving [$parameter] in class {$parameter->getDeclaringClass()->getName()}";

            throw new \Exception($message);
        }

        protected function resolveClass(\ReflectionParameter $parameter)
        {
            try {
                return $this->make($parameter->getClass()->name);
            }

            catch (\BindingResolutionException $e) {
                if ($parameter->isOptional()) {
                    return $parameter->getDefaultValue();
                }

                throw $e;
            }
        }

        protected function keyParametersByArgument(array $dependencies, array $args)
        {
            foreach ($args as $key => $value) {
                if (is_numeric($key)) {
                    unset($args[$key]);

                    $args[$dependencies[$key]->name] = $value;
                }
            }

            return $args;
        }

        public function resolving($toResolve, Closure $callback = null)
        {
            if ($callback === null && $toResolve instanceof Closure) {
                $this->resolvingCallback($toResolve);
            } else {
                $this->resolvingCallbacks[$toResolve][] = $callback;
            }
        }

        public function afterResolving($toResolve, Closure $callback = null)
        {
            if ($toResolve instanceof Closure && $callback === null) {
                $this->afterResolvingCallback($toResolve);
            } else {
                $this->afterResolvingCallbacks[$toResolve][] = $callback;
            }
        }

        protected function resolvingCallback(Closure $callback)
        {
            $toResolve = $this->getFunctionHint($callback);

            if ($toResolve) {
                $this->resolvingCallbacks[$toResolve][] = $callback;
            } else {
                $this->globalResolvingCallbacks[] = $callback;
            }
        }

        protected function afterResolvingCallback(Closure $callback)
        {
            $toResolve = $this->getFunctionHint($callback);

            if ($toResolve) {
                $this->afterResolvingCallbacks[$toResolve][] = $callback;
            } else {
                $this->globalAfterResolvingCallbacks[] = $callback;
            }
        }

        protected function getFunctionHint(Closure $callback)
        {
            $function = new \ReflectionFunction($callback);

            if ($function->getNumberOfParameters() == 0) {
                return null;
            }

            $expected = $function->getParameters()[0];

            if (!$expected->getClass()) {
                return null;
            }

            return $expected->getClass()->name;
        }

        protected function fireResolvingCallbacks($toResolve, $object)
        {
            $this->fireCallbackArray($object, $this->globalResolvingCallbacks);

            $this->fireCallbackArray(
                $object,
                $this->getCallbacksForType(
                    $toResolve,
                    $object,
                    $this->resolvingCallbacks
                )
            );

            $this->fireCallbackArray($object, $this->globalAfterResolvingCallbacks);

            $this->fireCallbackArray(
                $object,
                $this->getCallbacksForType(
                    $toResolve,
                    $object,
                    $this->afterResolvingCallbacks
                )
            );
        }

        protected function getCallbacksForType($toResolve, $object, array $callbacksPerType)
        {
            $results = [];

            foreach ($callbacksPerType as $type => $callbacks) {
                if ($type === $toResolve || $object instanceof $type) {
                    $results = array_merge($results, $callbacks);
                }
            }

            return $results;
        }

        protected function fireCallbackArray($object, array $callbacks)
        {
            foreach ($callbacks as $callback) {
                $callback($object, $this);
            }
        }

        public function isShared($toResolve)
        {
            if (isset($this->bindings[$toResolve]['shared'])) {
                $shared = $this->bindings[$toResolve]['shared'];
            } else {
                $shared = false;
            }

            return isset($this->instances[$toResolve]) || $shared === true;
        }

        protected function isBuildable($real, $toResolve)
        {
            return $real === $toResolve || $real instanceof Closure;
        }

        protected function getAlias($toResolve)
        {
            if (!is_array($toResolve) && !is_object($toResolve)) {
                return isset($this->aliases[$toResolve]) ? $this->aliases[$toResolve] : $toResolve;
            }
        }

        public function getBindings()
        {
            return $this->bindings;
        }

        protected function dropStaleInstances($toResolve)
        {
            unset($this->instances[$toResolve], $this->aliases[$toResolve]);
        }

        public function forgetInstance($toResolve)
        {
            unset($this->instances[$toResolve]);
        }

        public function forgetInstances()
        {
            $this->instances = [];
        }

        public function flush()
        {
            $this->aliases = $this->resolved = $this->bindings = $this->instances = [];
        }

        public static function getInstance()
        {
            $i = static::$instance;

            if (!$i) {
                static::makeInstance();
            }

            return static::$instance;
        }

        public static function setInstance($container)
        {
            static::$instance = $container;

            return $container;
        }

        public static function makeInstance()
        {
            static::$instance = new self;
        }

        public static function i()
        {
            $i = static::$instance = new self;

            return $i;
        }

        public function offsetExists($key)
        {
            return isset($this->bindings[$key]);
        }

        public function offsetGet($key)
        {
            return $this->make($key);
        }

        public function offsetSet($key, $value)
        {
            if (!$value instanceof Closure) {
                $value = function() use ($value) {
                    return $value;
                };
            }

            $this->bind($key, $value);
        }

        public function offsetUnset($key)
        {
            unset(
                $this->bindings[$key],
                $this->instances[$key],
                $this->resolved[$key]
            );
        }

        public function __get($key)
        {
            return $this->bindings[$key];
        }

        public function __set($key, $value)
        {
            $this->bindings[$key] = $value;

            return $this;
        }

        public function __isset($key)
        {
            return isset($this->bindings[$key]);
        }

        public function has($key)
        {
            return isset($this->bindings[$key]);
        }

        public function get($key, $default = null)
        {
            return isAke($this->bindings, $key, $default);
        }

        public function set($key, $value)
        {
            $this->bindings[$key] = $value;

            return $this;
        }

        public function load($class = null, array $args = [])
        {
            if (empty($class)) {
                return self::getInstance();
            }

            if ($class[0] != '\\') {
                return lib($class, [$args]);
            }

            return self::getInstance()->make($class, $args);
        }

        public function provider($toResolve, $real = null)
        {
            $this->bind($toResolve, $real, true);
        }

        public static function __callStatic(string $method, array $parameters)
        {
            if ('self' === $method) {
                return getContainer();
            }
        }
    }

    interface AppInterface
    {
        public function bound($toResolve);
        public function alias($toResolve, $alias);
        public function tag($toResolves, $tags);
        public function tagged($tag);
        public function bind($toResolve, $real = null, $shared = false);
        public function bindIf($toResolve, $real = null, $shared = false);
        public function singleton($toResolve, $real = null);
        public function extend($toResolve, Closure $closure);
        public function instance($toResolve, $instance);
        public function when($real);
        public function make($toResolve, $args = array());
        public function call($callback, array $args = array(), $defaultMethod = null);
        public function resolved($toResolve);
        public function resolving($toResolve, Closure $callback = null);
        public function afterResolving($toResolve, Closure $callback = null);
    }

    interface Contextual
    {
        public function needs($toResolve);
        public function give($implementation);
    }
