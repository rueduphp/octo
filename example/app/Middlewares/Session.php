<?php
namespace App\Middlewares;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Octo\FastMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Session extends FastMiddleware
{
    /**
     * @param ServerRequestInterface $request
     * @param DelegateInterface $delegate
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        if ('POST' === $request->getMethod()) {
            $body = $request->getParsedBody();

            $method = \Octo\isAke($body, '_method', 'octodummy');

            if ('octodummy' !== $method && in_array(mb_strtoupper($method), ['PUT', 'DELETE'])) {
                $request = $request->withMethod(mb_strtoupper($method));
            }
        }

        return $delegate->process($request);
    }
}