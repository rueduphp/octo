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
     * @param DelegateInterface|null $next
     * @return \GuzzleHttp\Psr7\Response|mixed|null|ResponseInterface
     * @throws Exception
     * @throws \ReflectionException
     */
    public function process(ServerRequestInterface $request, ?DelegateInterface $next = null)
    {
        $app    = $this->getContainer();
        $route  = $app->define('route');

        if (!is_null($route)) {
            /** @var Ultimate $session */
            $session = $this->getSession();
            /** @var FastRequest $req */
            $req = gi()->make(FastRequest::class);

            if ($req->method() === 'GET' && !$req->ajax() && !IS_CLI) {
                $session->setPreviousUrl(Url::full(), $route);
            }

            $filters = puller('routes.middlewares', $route->getName());

            if (null !== $filters) {
                if (!is_array($filters)) {
                    $filters = [$filters];
                }

                foreach ($filters as $ind => $filter) {
                    unset($filters[$ind]);

                    if (!empty($filters)) {
                        pusher('routes.middlewares', [$route->getName() => $filters]);
                    }

                    if ($filter instanceof Closure) {
                        return gi()->makeClosure($filter, $request, $this);
                    } else {
                        $instance = gi()->make($filter);

                        return gi()->call($instance, 'process', $request, $this);
                    }
                }
            }

            $middleware = $route->getMiddleware();

            if (is_array($middleware)) {
                $module = $middleware[0];
                $action = $middleware[1];

                $response = gi()->call($module, $action);
            } else {
                if ($middleware instanceof Closure) {
                    $ref        = new \ReflectionFunction($middleware);
                    $scope      = $ref->getClosureScopeClass();
                    $module     = getCore('modules.' . $scope->getName());
                    $response   = gi()->makeClosure($middleware, $request, $next);
                } else {
                    $action     = Arrays::last(explode('.', $route->getName()));
                    $module     = gi()->make($middleware);
                    $parameters = [$module, 'process', $action, $request, $app];
                    $response   = gi()->call(...$parameters);
                }
            }

            if (isset($module) && null !== $module) {
                actual('fast.module', $module);
            }

            if ($response instanceof ResponseInterface) {
                return $response;
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

            $this->exception('fastmiddlewaredispatch', 'The response is not valid.');
        }

        return $next->process($request);
    }
}
