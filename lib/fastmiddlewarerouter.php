<?php
namespace Octo;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Psr\Http\Message\ServerRequestInterface;

class Fastmiddlewarerouter extends FastMiddleware
{
    /**
     * @param ServerRequestInterface $request
     * @param DelegateInterface $next
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     * @throws \ReflectionException
     */
    public function process(ServerRequestInterface $request, DelegateInterface $next)
    {
        $app = $this->getContainer();

        /**
         * @var $route FastRouteInterface
         */
        $route = $app->router()->match($request);

        if (!is_null($route)) {
            $params = $route->getParams();

            foreach ($params as $key => $value) {
                $request = $request->withAttribute($key, $value);
            }

            $app->define('route', $route);
            In::set('actual.route', $route);
        }

        return $next->process($request);
    }
}
