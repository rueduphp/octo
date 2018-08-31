<?php
namespace Octo;

use Closure;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Fastmiddlewaredispatch extends FastMiddleware implements DelegateInterface
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
                if ($module instanceof Module) {
                    $response = $this->initModule($request, $module);

                    if (empty($response)) {
                        $response = gi()->call($module, $action);
                    }
                } else {
                    $response = gi()->call($module, $action);
                }
            } else {
                if ($middleware instanceof Closure) {
                    $ref        = new \ReflectionFunction($middleware);
                    $scope      = $ref->getClosureScopeClass();
                    $module     = getCore('modules.' . $scope->getName());

                    if (null !== $module) {
                        $response = $this->initModule($request, $module);
                    }

                    if (empty($response)) {
                        $response = gi()->makeClosure($middleware, $request, $next);
                    }
                } else {
                    $action     = Arrays::last(explode('.', $route->getName()));
                    $module     = gi()->make($middleware);
                    $this->initModule($request, $module);
                    $parameters = [$module, 'process', $action, $request, $app];
                    $response   = gi()->call(...$parameters);
                }
            }

            if ($response instanceof ResponseInterface) {
                return $response;
            }

            if (in_array($request->getMethod(), ['GET', 'HEAD']) && is_string($response) || is_numeric($response)) {
                $etag       = md5($response);
                $response   = $app->response(200, [], $response);
                $response   = $response->withHeader('Etag', $etag);

                if ($request->hasHeader('if-none-match')) {dd('ici');
                    if ($request->getHeaderLine('if-none-match') === $etag) {
                        $response = $response->withStatus(304);
                    }
                }

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

            if (jsonable($response)) {
                return $app->response(
                    200,
                    ['content-type' => 'application/json; charset=utf-8'],
                    $response->toJson(JSON_PRETTY_PRINT)
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

    /**
     * @param $module
     * @throws \ReflectionException
     */
    protected function initModule($request, Module $module)
    {
        $app = $this->getContainer();

        $methods = get_class_methods($module);

        if (in_array('init', $methods)) {
            $middlewares = gi()->call($module, 'init', $app);

            if (!empty($middlewares)) {
                $response = null;

                foreach ($middlewares as $middleware) {
                    if (empty($response)) {
                        $response = gi()->call(gi()->make($middleware), 'process', $request);
                    }
                }

                if (!empty($response)) {
                    return $response;
                }
            }
        }

        if (in_array('boot', $methods)) {
            gi()->call($module, 'boot', $app);
        }

        if (in_array('config', $methods)) {
            gi()->call($module, 'config', $app);
        }

        if (in_array('di', $methods)) {
            gi()->call($module, 'di', $app);
        }

        if (in_array('twig', $methods)) {
            gi()->call($module, 'twig', $app);
        }

        if (in_array('policies', $methods)) {
            gi()->call($module, 'policies', $app);
        }

        if (in_array('events', $methods)) {
            gi()->call($module, 'events', gi()->make(FastEvent::class), $app);
        }

        setCore('module', $module);
    }
}
