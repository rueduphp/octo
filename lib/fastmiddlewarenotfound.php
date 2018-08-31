<?php
namespace Octo;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Psr\Http\Message\ServerRequestInterface;

class Fastmiddlewarenotfound extends FastMiddleware
{
    /**
     * @param ServerRequestInterface $request
     * @param DelegateInterface $next
     * @return \GuzzleHttp\Psr7\Response|\Psr\Http\Message\ResponseInterface
     * @throws \ReflectionException
     */
    public function process(ServerRequestInterface $request, ?DelegateInterface $next = null)
    {
        $app = $this->getContainer();

        $tpl = $app->define("404.tpl") ?? in(404);

        if (is_null($tpl)) {
            $tpl = function () {
               return  '<h1>Error 404</h1>';
            };
        }

        return $app->response(404, [], !is_string($tpl) ? callThat($tpl) : $tpl);
    }
}
