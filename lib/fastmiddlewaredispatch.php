<?php
namespace Octo;

use Closure;
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
    public function process(ServerRequestInterface $request, ?DelegateInterface $next = null)
    {
        $app    = $this->getContainer();
        $route  = $app->define('route');

        if (!is_null($route)) {
            $action = Arrays::last(explode('.', $route->getName()));

            $middleware = $route->getMiddleware();

            if (is_array($middleware)) {
                $module = $middleware[0];
                $action = $middleware[1];
                $filter = puller('routes.middlewares', $route->getName());

                if (null !== $filter) {
                    $instance = gi()->make($filter);

                    return gi()->call($instance, 'process', $request, $this);
                }

                $response = gi()->call($module, $action);
            } else {
                if ($middleware instanceof Closure) {
                    $response = gi()->makeClosure($middleware, $request, $next);
                } else {
                    $module     = gi()->make($middleware);
                    $callable   = [$module, 'run'];
                    $response   = call_user_func_array($callable, [$action, $request, $app]);
                }
            }

            if (isset($module)) {
                actual('fast.module', $module);
            }

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
