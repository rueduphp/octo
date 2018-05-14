<?php
namespace Octo;

class Module
{
    use Framework;

    /** @var null|string */
    protected $viewPath;

    public function __construct()
    {
        In::self()['module'] = $this;

        if ($path = in_path('views')) {
            $this->setViewPath($path);
        }
    }

    /**
     * @param string $class
     * @throws \ReflectionException
     */
    public function middleware(string $class)
    {
        $di = $this->getContainer();

        $middleware = $di->resolve($class);

        gi()->call(
            $middleware,
            'process',
            $di->getRequest(),
            $di
        );
    }

    /**
     * @param string $class
     * @throws \ReflectionException
     */
    public function filter(string $class)
    {
        $middlewares = Setup::getMiddlewares();

        $middleware = isAke($middlewares, $class, null);

        if (is_string($middleware) && class_exists($middleware)) {
            $class = gi()->factory($class);

            gi()->call(
                $class,
                'process',
                getRequest()
            );
        }
    }

    /**
     * @param string $route
     * @param array $params
     * @throws \ReflectionException
     */
    public function redirect(string $route, array $params = [])
    {
        $app = $this->getContainer();
        $response = $app->redirectRouteResponse($route, $params);

        $app->render($response);
    }

    /**
     * @param string $action
     * @param null $module
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function forward(string $action, $module = null)
    {
        if (!is_null($module) && !is_object($module)) {
            $module = $this->getContainer()->resolve($module);
        } else {
            $module = $this;
        }

        return gi()->call($module, $action);
    }

    /**
     * @param callable $callable
     *
     * @return Moduleaction
     */
    public function action(callable $callable)
    {
        return new Moduleaction($callable);
    }

    /**
     * @return null|string
     */
    public function getViewPath(): ?string
    {
        return $this->viewPath;
    }

    /**
     * @param $viewPath
     * @return Module
     */
    public function setViewPath(string $viewPath): self
    {
        $this->viewPath = $viewPath;

        return $this;
    }

    /**
     * @param string $name
     * @param array $parameters
     * @return string
     * @throws \ReflectionException
     */
    public function render(string $name, array $parameters = [])
    {
        $blade = bladeFactory([$this->viewPath]);

        if (!isset($parameters['errors'])) {
            $parameters['errors'] = coll();
        }

        return $blade->make($name, (array) $parameters)->render();
    }
}
