<?php
    namespace Octo;

    use Interop\Http\ServerMiddleware\DelegateInterface;
    use Interop\Http\ServerMiddleware\MiddlewareInterface;
    use Psr\Http\Message\ServerRequestInterface;

    class Fastmiddlewaretrailingslash implements MiddlewareInterface
    {
        public function process(ServerRequestInterface $request, DelegateInterface $next)
        {
            $app = actual('fast');

            $uri = $request->getUri()->getPath();

            if (!empty($uri) && fnmatch('*/', $uri)) {
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
