<?php
    namespace Octo;

    use Interop\Http\ServerMiddleware\DelegateInterface;
    use Psr\Http\Message\ServerRequestInterface;

    class Fastmiddlewaretrailingslash extends FastMiddleware
    {
        /**
         * @param ServerRequestInterface $request
         * @param DelegateInterface $next
         * @return \GuzzleHttp\Psr7\MessageTrait|\GuzzleHttp\Psr7\Response|\Psr\Http\Message\ResponseInterface
         * @throws \ReflectionException
         */
        public function process(ServerRequestInterface $request, DelegateInterface $next)
        {
            $app = $this->getContainer();

            $uri = $request->getUri()->getPath();

            if (!empty($uri) && '/' !== $uri && fnmatch('*/', $uri)) {
                return $app->redirectResponse(substr($uri, 0, -1));
            }

            $body = $request->getParsedBody();

            $method = isAke($body, '_method', 'octodummy');

            if ('octodummy' !== $method && in_array($method, ['PUT', 'DELETE'])) {
                $request = $request->withMethod($method);
            }

            return $next->process($request);
        }
    }
