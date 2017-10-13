<?php
namespace Octo;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;

class Fastmiddlewarenotfound implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, DelegateInterface $next)
    {
        $app = actual('fast');

        return $app->response(404, [], '<h1>Error 404</h1>');
    }
}
