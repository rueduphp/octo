<?php
namespace Octo;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Psr\Http\Message\ServerRequestInterface;

class Fastmiddlewareredirect extends FastMiddleware
{
    public function process(ServerRequestInterface $request, DelegateInterface $next)
    {
        $container  = $this->getContainer();
        $url        = $container->define('redirect');

        return $container->redirectResponse($url);
    }
}