<?php
namespace Octo;

class Module implements FastModuleInterface
{
    use Framework;

    /** @var null|string */
    protected $viewPath;

    /** @var FastRequest */
    protected $request;

    /** @var array */
    protected $vars = [];

    /**
     * @throws \ReflectionException
     */
    public function __construct()
    {
        if ($path = in_path('views')) {
            $this->setViewPath($path);
        }

        setCore('modules.' . get_called_class(), $this);

        $this->request = gi()->make(FastRequest::class);
    }

    /**
     * @param $concern
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function callMiddleware($concern)
    {
        if ($concern instanceof \Closure) {
            return gi()->makeClosure($concern, $this->getContainer()->getRequest());
        }

        $di = $this->getContainer();

        $middleware = $di->resolve($concern);

        return gi()->call(
            $middleware,
            'process',
            $di->getRequest(),
            $di
        );
    }

    /**
     * @return mixed|object|FastRequest|Request
     * @throws \ReflectionException
     */
    public function saveFiles()
    {
        $request = $this->request;

        if (!is_dir(public_path('uploads'))) {
            mkdir(public_path('uploads'), 0777);
            mkdir(public_path('uploads/thumb'), 0777);
        } else {
            if (!is_dir(public_path('uploads/thumb'))) {
                mkdir(public_path('uploads/thumb'), 0777);
            }
        }

        $finalRequest = $request;

        foreach ($request->all() as $key => $value) {
            if ($request->hasFile($key)) {
                if ($request->has($key . '_max_width') && $request->has($key . '_max_height')) {
                    $filename = token() . '-' . $request->file($key)->getClientFilename();
                    $file     = $request->file($key);
                    $image    = image()->make($file);

                    image()->make($file)->resize(50, 50)->save(public_path('uploads/thumb') . '/' . $filename);

                    $width  = $image->width();
                    $height = $image->height();

                    if ($width > $request->{$key . '_max_width'} && $height > $request->{$key . '_max_height'}) {
                        $image->resize($request->{$key . '_max_width'}, $request->{$key . '_max_height'});
                    } elseif ($width > $request->{$key . '_max_width'}) {
                        $image->resize($request->{$key . '_max_width'}, null, function ($constraint) {
                            $constraint->aspectRatio();
                        });
                    } elseif ($height > $request->{$key . '_max_width'}) {
                        $image->resize(null, $request->{$key . '_max_height'}, function ($constraint) {
                            $constraint->aspectRatio();
                        });
                    }

                    $image->save(public_path('uploads') . '/' . $filename);
                } else {
                    $filename = token() . '-' . $request->file($key)->getClientOriginalName();
                    $request->file($key)->moveTo(public_path('uploads' . '/' . $filename));
                }

                $finalRequest = $request->make($request->native()->withAttribute($key, $filename));
            }
        }

        return $finalRequest;
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
     * @throws Exception
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

        $vars = viewParams();

        foreach ($this->vars as $key => $value) {
            $parameters[$key] = $value;
        }

        foreach ($vars as $key => $value) {
            $parameters[$key] = $value;
        }

        $parameters['errors'] = $parameters['errors'] ?? coll();

        setCore('blade.context', $parameters);

        return $blade->make($name, (array) $parameters)->render();
    }

    /**
     * @param string $key
     * @param $value
     */
    public function __set(string $key, $value)
    {
        $this->vars[$key] = $value;
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function __get(string $key)
    {
        return $this->vars[$key] ?? null;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function __isset(string $key)
    {
        return isset($this->vars[$key]);
    }

    /**
     * @param string $key
     */
    public function __unset(string $key)
    {
        unset($this->vars[$key]);
    }

    /**
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public function __call(string $method, array $args)
    {
        if (function_exists('\\' . $method)) {
            $method = '\\' . $method;

            return $method(...$args);
        } elseif (function_exists('\App\\' . $method)) {
            $method = '\App\\' . $method;

            return $method(...$args);
        } elseif (function_exists('\Octo\\' . $method)) {
            $method = '\Octo\\' . $method;

            return $method(...$args);
        }
    }

    /**
     * @return array
     */
    public function getVars(): array
    {
        return $this->vars;
    }
}

class ModuleMiddleware
{
    /**
     * @throws \ReflectionException
     */
    public function __construct()
    {
        $this->before();
        afterModule([$this, 'after']);
    }

    public function before() {}
    public function after() {}
}
