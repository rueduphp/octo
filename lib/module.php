<?php
    namespace Octo;

    class Module
    {
        use Framework;

        /**
         * @param string $class
         */
        public function middleware(string $class)
        {
            $di = $this->getContainer();

            $middleware = $di->resolve($class);

            instanciator()->call(
                $middleware,
                'process',
                $di->getRequest(),
                $di
            );
        }

        /**
         * @param string $route
         * @param array $params
         */
        public function redirect(string $route, array $params = [])
        {
            $app = $this->getContainer();
            $response = $app->redirectRouteResponse($route, $params);

            $app->render($response);
        }

        /**
         * @param string $action
         * @param null $module
         *
         * @return mixed
         */
        public function forward(string $action, $module = null)
        {
            if (!is_null($module) && !is_object($module)) {
                $module = $this->getContainer()->resolve($module);
            } else {
                $module = $this;
            }

            return instanciator()->call($module, $action);
        }
    }
