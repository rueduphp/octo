<?php
    namespace Octo;

    use Interop\Http\ServerMiddleware\DelegateInterface;
    use Interop\Http\ServerMiddleware\MiddlewareInterface;
    use Psr\Http\Message\ServerRequestInterface;

    class Fastmiddlewarenext implements MiddlewareInterface
    {
        public function process(ServerRequestInterface $request, DelegateInterface $next)
        {
            return $next->process($request);
        }
    }
