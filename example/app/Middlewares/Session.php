<?php
namespace App\Middlewares;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Octo\FastMiddleware;
use Octo\FastRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Session extends FastMiddleware
{
    /**
     * @var FastRequest
     */
    private $coreRequest;

    public function __construct(FastRequest $coreRequest)
    {
        $this->coreRequest = $coreRequest;
    }

    /**
     * @param ServerRequestInterface $request
     * @param DelegateInterface $delegate
     * @return ResponseInterface
     * @throws \ReflectionException
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        setViewVar('olds', session()->pull('__oldinputs', []));

        if ('POST' === $request->getMethod()) {
            $body = $request->getParsedBody();

            $method = \Octo\isAke($body, '_method', 'octodummy');

            if ('octodummy' !== $method && in_array(mb_strtoupper($method), ['PUT', 'DELETE'])) {
                $request = $request->withMethod(mb_strtoupper($method));
            }

            session()->set('__oldinputs', $this->coreRequest->post());
        }

        return $delegate->process($request);
    }
}
