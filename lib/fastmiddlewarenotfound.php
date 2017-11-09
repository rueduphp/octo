<?php
namespace Octo;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Psr\Http\Message\ServerRequestInterface;

class Fastmiddlewarenotfound extends FastMiddleware
{
    public function process(ServerRequestInterface $request, DelegateInterface $next)
    {
        $app = $this->getContainer();

        $tpl = $app->define("404.tpl");

        if (!is_callable($tpl)) {
            $tpl = function () {
               return  '<h1>Error 404</h1>';
            };
        }

        return $app->response(404, [], $tpl());
    }
}
