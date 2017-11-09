<?php
namespace Octo;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Psr\Http\Message\ServerRequestInterface;

use function Http\Response\send as go;

class Ffastmiddlewareview extends FastMiddleware
{
    public function process(ServerRequestInterface $request, DelegateInterface $next)
    {
        $container = $this->getContainer();
        $renderer = $container->getRenderer();

        $page = $container->define('page');
        $args = $container->define('args');

        return go($renderer->render($page, $args));
    }
}