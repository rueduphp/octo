<?php
namespace Octo;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Fastmiddlewaredispatch extends FastMiddleware
{
    /**
     * @param ServerRequestInterface $request
     * @param DelegateInterface $next
     * @return \GuzzleHttp\Psr7\Response|mixed|null|ResponseInterface
     * @throws \ReflectionException
     */
    public function process(ServerRequestInterface $request, DelegateInterface $next)
    {
        $app    = $this->getContainer();
        $route  = $app->define('route');

        if (!is_null($route)) {
            $action = Arrays::last(explode('.', $route->getName()));

            $middleware = $route->getMiddleware();

            if (is_array($middleware)) {
                $module = $middleware[0];
                $action = $middleware[1];
                $response = gi()->call($module, $action);
            } else {
                $module     = gi()->make($middleware);
                $callable   = [$module, 'run'];
                $response   = call_user_func_array($callable, [$action, $request, $app]);
            }

            actual('fast.module', $module);

            if (is_string($response) || is_numeric($response)) {
                return $app->response(200, [], $response);
            }

            if (is_array($response)) {
                return $app->response(
                    200,
                    ['content-type' => 'application/json; charset=utf-8'],
                    json_encode($response, JSON_PRETTY_PRINT)
                );
            }

            if (arrayable($response)) {
                return $app->response(
                    200,
                    ['content-type' => 'application/json; charset=utf-8'],
                    json_encode($response->toArray(), JSON_PRETTY_PRINT)
                );
            }

            if ($response instanceof ResponseInterface) {
                return $response;
            }

            $this->exception('fastmiddlewaredispatch', 'The response is not valid.');
        }

        return $next->process($request);
    }
}
