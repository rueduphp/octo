<?php
    namespace Octo;

    use Interop\Http\ServerMiddleware\DelegateInterface;
    use Psr\Http\Message\ServerRequestInterface;

    class Fastmiddlewareacl extends FastMiddleware
    {
        public function process(ServerRequestInterface $request, DelegateInterface $next)
        {
            try {
                $response = $next->process($request);
            } catch (\Exception $e) {
                if ($e instanceof AuthmiddlewareException) {
                    $app = $this->fast();

                    return $app->redirectResponse($app->getAuth()->getLoginPath());
                } else {
                    throw $e;
                }
            }

            return $response;
        }
    }
