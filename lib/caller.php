<?php
    namespace Octo;

    class Caller
    {
        protected function callWithDependencies($instance, $method, array $parameters = [])
        {
            return call_user_func_array(
                [$instance, $method], $this->resolveClassMethodDependencies($parameters, $instance, $method)
            );
        }

        protected function resolveClassMethodDependencies(array $parameters, $instance, $method)
        {
            if (!method_exists($instance, $method)) {
                return $parameters;
            }

            return $this->resolveMethodDependencies(
                $parameters, new \ReflectionMethod($instance, $method)
            );
        }

        public function resolveMethodDependencies(array $parameters, \ReflectionFunctionAbstract $reflector)
        {
            foreach ($reflector->getParameters() as $key => $parameter) {
                $class = $parameter->getClass();

                if ($class && !$this->alreadyInParameters($class->name, $parameters)) {
                    array_splice(
                        $parameters,
                        $key,
                        0,
                        [$this->container->make($class->name)]
                    );
                }
            }

            return $parameters;
        }

        protected function alreadyInParameters($class, array $parameters)
        {
            return !is_null(Arrays::firstOne($parameters, function($key, $value) use ($class) {
                return $value instanceof $class;
            }));
        }

        public function make($class, array $args = [])
        {
            return lib('app')->getInstance()->make($class, $args);
        }
    }
