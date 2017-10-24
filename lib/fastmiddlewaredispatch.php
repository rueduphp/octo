<?php
namespace Octo;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Fastmiddlewaredispatch extends FastMiddleware
{
    public function process(ServerRequestInterface $request, DelegateInterface $next)
    {
        $app    = $this->fast();
        $route  = $app->define('route');

        if (!is_null($route)) {
            $action = Arrays::last(explode('.', $route->getName()));

            $middleware = $route->getMiddleware();

            if (is_array($middleware)) {
                $module = $middleware[0];
                $action = $middleware[1];
//                $callable = $middleware;

//                $response = call_user_func_array($callable, [$request, $app]);
                $response = callMethod($module, $action, $request, $app);
            } else {
                $module = $this->maker($middleware);

                $callable = [$module, 'run'];

                $response = call_user_func_array($callable, [$action, $request, $app]);
            }

            actual('fast.module', $module);

            if (is_string($response) || is_numeric($response)) {
                return $app->response(200, [], $response);
            }

            if (is_array($response)) {
                return $app->response(
                    200,
                    ['content-type' => 'application/json; charset=utf-8'],
                    json_encode($response)
                );
            }

            if (arrayable($response)) {
                return $app->response(
                    200,
                    ['content-type' => 'application/json; charset=utf-8'],
                    json_encode($response->toArray())
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
