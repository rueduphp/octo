<?php
namespace Octo;

use Closure;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Smart implements DelegateInterface
{
    /** @var Fast  */
    private $app;

    /** @var array  */
    private $callbacks = [];

    public function __construct()
    {
        $this->app = App::create();
    }

    /**
     * @param $next
     * @return Smart
     */
    public function step($next): self
    {
        $this->callbacks[] = $next;

        return $this;
    }

    /**
     * @return mixed|null|object
     * @throws \ReflectionException
     */
    private function getNext()
    {
        $callbacks    = $this->callbacks;
        $callback     = array_shift($callbacks);

        $this->callbacks = $callbacks;

        if (is_string($callback)) {
            return instanciator()->singleton($callback);
        } elseif (is_callable($callback) && !$callback instanceof Closure) {
            $callback = call_user_func_array($callback, [$this]);

            if (is_string($callback)) {
                return instanciator()->singleton($callback);
            } else {
                return $callback;
            }
        } else {
            return $callback;
        }
    }

    /**
     * @param null $request
     * @return mixed|null|ResponseInterface
     * @throws \ReflectionException
     */
    public function run($request = null)
    {
        if ($this->app->getRenderer() instanceof FastTwigRenderer) {
            $this->app->add('twig_extensions', FastTwigExtension::class);
            $this->app->applyTwigExtensions();
        }

        if (is_null($request)) {
            $request = $this->app->getRequest();
        }

        return $this->process($request);
    }

    /**
     * @param ServerRequestInterface $request
     * @return mixed|null|ResponseInterface
     * @throws \ReflectionException
     */
    public function process(ServerRequestInterface $request)
    {
        $this->app->setRequest($request);

        $middleware = $this->getNext();

        if (is_null($middleware)) {
            exception('smart', 'no middleware intercepts request');
        } elseif ($middleware instanceof MiddlewareInterface) {
            $methods = get_class_methods($middleware);

            if (in_array('process', $methods)) {
                $params = array_merge([$middleware, 'process'], [$request, $this]);
                $response = gi()->call(...$params);
            } elseif (in_array('handle', $methods)) {
                $params = array_merge([$middleware, 'handle'], [$request, $this]);
                $response = gi()->call(...$params);
            }
        } elseif (is_callable($middleware)) {
            if (is_array($middleware)) {
                $params = array_merge($middleware, [$request, [$this, 'process']]);
                $response = gi()->call(...$params);
            } elseif ($middleware instanceof Closure) {
                $params = array_merge([$middleware], [$request, [$this, 'process']]);
                $response = gi()->makeClosure(...$params);
            } else {
                $params = array_merge([$middleware, '__invoke'], [$request, [$this, 'process']]);
                $response = gi()->call(...$params);
            }
        }

        $this->app->setResponse($response);

        return $response;
    }

    /**
     * @return Fast
     */
    public function getApp(): Fast
    {
        return $this->app;
    }
}