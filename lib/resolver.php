<?php
    namespace Octo;

    use ReflectionClass;

    class Resolver
    {
        public function setSingleton($singleton)
        {
            $singletons = Registry::get('octo.resolver.singletons', []);

            $r = new ReflectionClass($singleton);

            $singletons[$r->getName()] = $singleton;

            Registry::set('octo.resolver.singletons', $singletons);

            return $this;
        }

        public function set($class, callable $singleton)
        {
            $singletons = Registry::get('octo.resolver.singletons', []);

            $singletons[$class] = $singleton;

            Registry::set('octo.resolver.singletons', $singletons);

            return $this;
        }

        public function setFactory($class, callable $factory)
        {
            $factories = Registry::get('octo.resolver.factories', []);

            $factories[$class] = $factory;

            Registry::set('octo.resolver.factories', $factories);

            return $this;
        }

        public function get($class)
        {
            $factories = Registry::get('octo.resolver.factories', []);

            $resolver = isAke($factories, $class, null);

            if ($resolver) {
                return $resolver();
            } else {
                $singletons = Registry::get('octo.resolver.singletons', []);

                $resolver = isAke($singletons, $class, null);

                if ($resolver) {
                    return $resolver();
                } else {
                    if (class_exists($class)) {
                        $r = new ReflectionClass($class);
                        $constructor = $r->getConstructor();

                        $params = [];

                        if ($constructor) {
                            $parameters = $constructor->getParameters();

                            $params = [];

                            foreach ($parameters as $parameter) {
                                if ($parameter->getCalss()) {
                                    $params[] = $this->get($parameter->getClass()->getName());
                                } else {
                                    $params[] = $parameter->getDefaultValue();
                                }
                            }
                        }

                        return (new App)->make($class, $params);
                    } else {
                        throw new Exception("The class $class is not saved.");
                    }
                }
            }
        }

        /**
         * @param string $name
         * @param array $arguments
         *
         * @return mixed
         */
        public static function __callStatic(string $name, array $arguments)
        {
            return call_user_func_array([instanciator(), $name], $arguments);
        }
    }
