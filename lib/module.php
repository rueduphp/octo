<?php
    namespace Octo;

    use \Psr\Http\Message\ServerRequestInterface;

    class Module
    {
        use Framework;

        /**
         * @param string $action
         * @param ServerRequestInterface $request
         * @param Fast $app
         * 
         * @return mixed|null
         */
        public function run(string $action, ServerRequestInterface $request, Fast $app)
        {
            return instanciator()
                ->call(
                    $this,
                    $action,
                    $request,
                    $app
                )
            ;
        }

        /**
         * @param string $class
         */
        public function middleware(string $class)
        {
            $middleware = $this
                ->getContainer()
                ->resolve($class)
            ;

            instanciator()
                ->call(
                    $middleware,
                    'process',
                    $this
                        ->getContainer()
                        ->getRequest()
                )
            ;
        }
    }
