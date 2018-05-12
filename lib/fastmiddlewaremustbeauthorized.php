<?php

namespace Octo;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Fastmiddlewaremustbeauthorized extends FastMiddleware
{
    /**
     * @param ServerRequestInterface $request
     * @param DelegateInterface $next
     * @return ResponseInterface
     * @throws \ReflectionException
     */
    public function process(ServerRequestInterface $request, DelegateInterface $next)
    {
        $app = $this->getContainer();

        $path = $request->getUri()->getPath();

        if (!fnmatch('/admin*', $path)) {
            return $app->resolve(FastMiddleware::class)->process($request, $next);
        }

        $auth = $app->resolve(FastAuthInterface::class);

        $app->setAuth($auth);

        return $app->resolve(Fastmiddlewareauth::class)->process($request, $next);
    }
}