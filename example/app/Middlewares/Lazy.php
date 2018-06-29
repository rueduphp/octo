<?php
namespace App\Middlewares;

use App\Facades\Container;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Octo\FastMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Lazy extends FastMiddleware
{
    /**
     * @var string
     */
    private $middleware;

    public function __construct(string $middleware)
    {
        $this->middleware = $middleware;
    }

    /**
     * @param ServerRequestInterface $request
     * @param DelegateInterface $delegate
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        /** @var MiddlewareInterface $middleware */
        $middleware = Container::get($this->middleware);

        return $middleware->process($request, $delegate);
    }
}
