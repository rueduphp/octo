<?php
    namespace Octo;

    use ArrayAccess;
    use ArrayObject;
    use Closure;
    use Exception as NativeException;
    use GuzzleHttp\Psr7\Response as Psr7Response;
    use GuzzleHttp\Psr7\ServerRequest as Psr7Request;
    use GuzzleHttp\Psr7\Stream;
    use GuzzleHttp\Psr7\UploadedFile;
    use Illuminate\Filesystem\Filesystem;
    use Illuminate\Support\MessageBag;
    use Illuminate\View\Compilers\BladeCompiler;
    use Interop\Http\ServerMiddleware\DelegateInterface;
    use Interop\Http\ServerMiddleware\MiddlewareInterface;
    use Pagerfanta\Pagerfanta;
    use Pagerfanta\View\TwitterBootstrap4View;
    use Psr\Container\ContainerExceptionInterface;
    use Psr\Container\ContainerInterface;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Twig_Environment;
    use Twig_Extension;
    use Twig_Filter;
    use Twig_SimpleFunction;
    use TypeError;
    use Zend\Expressive\Router\FastRouteRouter as FastRouter;
    use Zend\Expressive\Router\Route as FastRoute;
    use function Http\Response\send as sendResponse;

    class Fast
        extends ArrayObject
        implements FastContainerInterface,
        ArrayAccess,
        DelegateInterface,
        ContainerInterface
    {
        use Hookable;

        /**
         * @var Fastcontainer
         */
        protected $app;

        protected $response, $request, $router, $middlewares = [], $extensionsLoaded = [], $started = false;

        /**
         * @throws \ReflectionException
         */
        public function __construct()
        {
            $this->app = gi()->make(Fastcontainer::class);

            $this->request = $this->fromGlobals();

            fast($this);
        }

        /**
         * @param string $key
         * @param $value
         *
         * @return Fast
         *
         * @throws \ReflectionException
         */
        public function setConfig(string $key, $value): self
        {
            $configs = get('fast.config', []);
            aset($configs, $key, $value);
            set('fast.config', $configs);

            return $this;
        }

        /**
         * @param string $key
         * @param null $value
         *
         * @return mixed|null
         *
         * @throws \ReflectionException
         */
        public function getConfig(string $key, $value = null)
        {
            $configs = get('fast.config', []);

            return aget($configs, $key, $value);
        }

        /**
         * @param string $key
         * @param $value
         *
         * @return Fast
         */
        public function bind(string $key, $value): self
        {
            $bound = Registry::get('fast.bound', []);
            aset($bound, $key, $value);
            Registry::set('fast.bound', $bound);

            return $this;
        }

        /**
         * @param string $key
         * @param null $value
         *
         * @return mixed
         */
        public function retrieve(string $key, $value = null)
        {
            $bound = Registry::get('fast.bound', []);

            return aget($bound, $key, $value);
        }

        /**
         * @param string $key
         * @param $value
         *
         * @return Fast
         */
        public function handle(string $key, $value): self
        {
            $handled = Registry::get('fast.handled', []);
            aset($handled, $key, $value);
            Registry::set('fast.handled', $handled);

            return $this;
        }

        /**
         * @param string $key
         * @param null $value
         *
         * @return array|mixed|null
         *
         * @throws \ReflectionException
         */
        public function handled(string $key, $value = null)
        {
            $handled = Registry::get('fast.handled', []);

            $value =  aget($handled, $key, $value);

            if (is_string($value) && class_exists($value)) {
                return [instanciator()->singleton($value), 'handle'];
            }

            return $value;
        }

        /**
         * @param $concern
         *
         * @return Fast
         */
        public function share($concern): self
        {
            if (is_object($concern)) {
                $class = get_class($concern);

                instanciator()->wire($class, $concern);
            } elseif (is_string($concern)) {
                $args   = func_get_args();
                $key    = array_shift($args);
                $value  = array_shift($args);

                instanciator()->wire($key, $value);
            }

            return $this;
        }

        /**
         * @param string $concern
         *
         * @return mixed
         */
        public function shared(string $concern)
        {
            return instanciator()->autowire($concern);
        }

        /**
         * @param string $key
         * @param $value
         *
         * @return Fast
         */
        public function add(string $key, $value): self
        {
            $concern = 'add.fast.' . $key;

            $old = $this->app->dataget($concern, []);

            $new = array_merge($old, [$value]);

            $this->app->dataset($concern, $new);

            return $this;
        }

        /**
         * @return ServerRequestInterface
         */
        public function fromGlobals(): ServerRequestInterface
        {
            $request = Psr7Request::fromGlobals();

            $this->setRequest($request);

            return $request;
        }

        /**
         * @param ServerRequestInterface $request
         *
         * @return Fast
         */
        public function setRequest(ServerRequestInterface $request): self
        {
            $this->request = $request;

            return $this;
        }

        /**
         * @param $user
         *
         * @return Fast
         */
        public function setUser($user): self
        {
            $this->define('user', $user);

            return $this;
        }

        public function getUser()
        {
            $user = handled('user');

            if ($user) {
                return $user;
            }

            $user = $this->define('user');

            handler('user', $user);

            return $user;
        }

        /**
         * @param $session
         *
         * @throws TypeError
         */
        private function testSession($session)
        {
            if (!is_array($session) && !$session instanceof ArrayAccess) {
                throw new TypeError('session is not valid');
            }
        }

        /**
         * @param $session
         *
         * @return Fast
         *
         * @throws TypeError
         */
        public function setSession($session): self
        {
            $this->testSession($session);
            $this->define('session', $session);

            return $this;
        }

        /**
         * @return array|Ultimate
         * @throws \ReflectionException
         */
        public function getSession()
        {
            $session = $this->define('session');

            if (is_null($session)) {
                return startSession();
            }

            $this->testSession($session);

            return $session ?: [];
        }

        /**
         * @param FastRendererInterface $renderer
         *
         * @return Fast
         */
        public function setRenderer(FastRendererInterface $renderer): self
        {
            $this->define('renderer', $renderer);

            return $this;
        }

        /**
         * @return FastPhpRenderer|FastTwigRenderer
         */
        public function getRenderer()
        {
            return $this->define('renderer');
        }

        public function applyTwigExtensions()
        {
            $extensions = $this->app->dataget('add.fast.twig_extensions', []);

            $twig = $this->getRenderer();

            foreach ($extensions as $extension) {
                if (!in_array($extension, $this->extensionsLoaded)) {
                    try {
                        $this->extensionsLoaded[] = $extension;
                        $twig->addExtension(
                            gi()->make($extension)
                        );
                    } catch (\Exception $e) {
                        $this->extensionsLoaded[] = $extension;
                    }
                }
            }
        }

        /**
         * @param mixed $auth
         *
         * @return $this
         */
        public function setAuth($auth)
        {
            $this->define('auth', $auth);

            return $this;
        }

        /**
         * @return FastAuthInterface
         */
        public function getAuth()
        {
            return $this->define('auth');
        }

        /**
         * @return ServerRequestInterface
         */
        public function getRequest()
        {
            return $this->request;
        }

        /**
         * @param string $concern
         * @param null $sinleton
         *
         * @return mixed|object
         *
         * @throws \ReflectionException
         */
        public function get($concern, $sinleton = null)
        {
            return $this->app->get($concern, $sinleton);
        }

        /**
         * @param $concern
         * @param $value
         * @return $this
         */
        public function set($concern, $value)
        {
            $this->app->set($concern, $value);

            return $this;
        }

        public function has($key)
        {
            return $this->app->datahas($key);
        }

        public function delete($key)
        {
            $this->app->datadel($key);

            return $this;
        }

        public function register($key, callable $callable)
        {
            $this->app->register($key, $callable);

            return $this;
        }

        public function fn($m, callable $c)
        {
            $this->app->cb($m, $c);

            return $this;
        }

        public function extend($m, callable $c)
        {
            return $this->fn($m, $c);
        }

        public function macro($m, callable $c)
        {
            return $this->fn($m, $c);
        }

        public function scope($m, callable $c)
        {
            return $this->fn($m, $c);
        }

        public function toArray()
        {
            return $this->app->toArray();
        }

        public function toJson()
        {
            return json_encode($this->toArray());
        }

        public function json()
        {
            echo $this->toJson();
        }

        public function __toString()
        {
            return $this->toJson();
        }

        public function __get($key)
        {
            return $this->app->dataget($key);
        }

        public function __set($key, $value)
        {
            if (class_exists($key)) {
                handler($key, $value);
            }

            return $this->set($key, $value);
        }

        public function __isset($key)
        {
            return $this->has($key);
        }

        public function __unset($key)
        {
            return $this->delete($key);
        }

        public function offsetSet($key, $value)
        {
            return $this->set($key, $value);
        }

        public function offsetExists($key)
        {
            return $this->has($key);
        }

        public function offsetUnset($key)
        {
            return $this->delete($key);
        }

        public function offsetGet($key)
        {
            return $this->app->dataget($key);
        }

        public function trigger()
        {
            return call_user_func_array([$this, 'emit'], func_get_args());
        }

        public function emit(...$args)
        {
            $event  = array_shift($args);

            $events = Registry::get('fast.app.events', []);

            $eventsToCall = isAke($events, $event, []);

            if (!empty($eventsToCall)) {
                $collection = [];

                foreach ($eventsToCall as $eventToCall) {
                    $collection[] = [
                        'event'     => $eventToCall,
                        'priority'  => $eventToCall->priority
                    ];
                }

                $listeners = array_values(
                    coll($collection)
                    ->sortByDesc('priority')
                    ->toArray()
                );

                $results = [];

                foreach ($listeners as $listenerCalled) {
                    $listener = $listenerCalled['event'];

                    $continue = true;

                    if ($listener->called) {
                        if ($listener->once === true) {
                            $continue = false;
                        }
                    }

                    if (!$continue) {
                        break;
                    } else {
                        $listener->called = true;
                        $result = call_user_func_array($listener->callable, $args);

                        if ($listener->halt) {
                            Registry::set('fast.app.events', []);

                            return $result;
                        } else {
                            $results[] = $result;
                        }
                    }
                }

                return $results;
            }
        }

        /**
         * @param $event
         * @param callable $callable
         * @param int $priority
         *
         * @return Listener
         */
        public function attach($event, callable $callable, $priority = 0)
        {
            return $this->on($event, $callable, $priority);
        }

        public function detach($event, callable $callable)
        {
            $events = Registry::get('fast.app.events', []);

            $events = array_filter($events, function ($event) use ($callable) {
                return $event->callable !== $callable;
            });

            Registry::set('fast.app.events', $events);

            return $this;
        }

        public function clearListeners()
        {
            Registry::set('fast.app.events', []);

            return $this;
        }

        /**
         * @param $event
         * @param callable $callable
         * @param int $priority
         *
         * @return Listener
         */
        public function on($event, callable $callable, $priority = 0)
        {
            $events = Registry::get('fast.app.events', []);

            if (!isset($events[$event])) {
                $events[$event] = [];
            }

            $priority = !is_int($priority) ? 0 : $priority;

            $ev = $events[$event][] = new Listener($callable, $priority);

            Registry::set('fast.app.events', $events);

            return $ev;
        }

        /**
         * @param ResponseInterface $response
         */
        public function render(ResponseInterface $response)
        {
            sendResponse($response);
        }

        /**
         * @param array ...$args
         *
         * @return mixed|null
         *
         * @throws \ReflectionException
         */
        public function call(...$args)
        {
            return gi()->call(...$args);
        }

        /**
         * @param array ...$args
         * @return mixed|object
         * @throws \ReflectionException
         */
        public function resolve(...$args)
        {
            return gi()->factory(...$args);
        }

        /**
         * @param string $driver
         *
         * @return Bcrypt
         *
         * @throws \ReflectionException
         */
        public function hasher($driver = Bcrypt::class)
        {
            return gi()->getOr($driver);
        }

        /**
         * @param array ...$args
         *
         * @return mixed|object
         *
         * @throws \ReflectionException
         */
        public function resolveOnce(...$args)
        {
            return gi()->make(...$args);
        }

        /**
         * @return Object
         */
        public function getContainer()
        {
            return $this->app;
        }

        /**
         * @param mixed ...$args
         * @return Psr7Response
         * @throws \ReflectionException
         */
        public function response(...$args): Psr7Response
        {
            return gi()->make(Psr7Response::class, $args, false);
        }

        /**
         * @param int $code
         * @param string $message
         * @return Psr7Response
         * @throws \ReflectionException
         */
        public function abort($code = 403, $message = 'Forbidden')
        {
            return $this->response($code, [], $message);
        }

        /**
         * @param $uri
         * @param int $status
         * @return \GuzzleHttp\Psr7\MessageTrait|Psr7Response
         * @throws \ReflectionException
         */
        public function redirectResponse($uri, int $status = 301)
        {
            $this->response = $this->response()
                ->withStatus($status)
                ->withHeader('Location', $uri)
            ;

            return $this->response;
        }

        /**
         * @param string $route
         * @param array $params
         * @param int $status
         * @return \GuzzleHttp\Psr7\MessageTrait|Psr7Response
         * @throws Exception
         * @throws \ReflectionException
         */
        public function redirectRouteResponse(string $route, array $params = [], int $status = 301)
        {
            $uri = $this->router()->urlFor($route, $params);

            return $this->redirectResponse($uri, $status);
        }

        /**
         * @param int $status
         * @return Psr7Response
         * @throws \ReflectionException
         */
        public function setStatus(int $status)
        {
            $this->response = $this->response()->withStatus($status);

            return $this->response;
        }

        /**
         * @param string $key
         * @param $value
         * @return \GuzzleHttp\Psr7\MessageTrait
         * @throws \ReflectionException
         */
        public function setHeader(string $key, $value)
        {
            $this->response = $this->response()->withHeader($key, $value);

            return $this->response;
        }

        /**
         * @param null $request
         *
         * @return mixed|null|ResponseInterface
         *
         * @throws TypeError         *
         * @throws \ReflectionException
         */
        public function run($request = null)
        {
            if ($this->getRenderer() instanceof FastTwigRenderer) {
                $this->add('twig_extensions', FastTwigExtension::class);
                $this->applyTwigExtensions();
            }

            if (!is_null($request)) {
                $this->request = $request;
            }

             return $this->process($this->request);
        }

        /**
         * @return bool
         * @throws TypeError
         */
        public function hasSession(): bool
        {
            return !is_null($this->getSession());
        }

        /**
         * @param ServerRequestInterface $request
         *
         * @return mixed|null|ResponseInterface
         *
         * @throws TypeError
         * @throws \ReflectionException
         */
        public function process(ServerRequestInterface $request)
        {
            if (false === $this->started && true === $this->hasSession()) {
                unset($this->getSession()['old_inputs']);
                $this->started = true;

                if ('post' === Inflector::lower($request->getMethod())) {
                    $this->getSession()->set('old_inputs', $request->getParsedBody() ?? []);
                }
            }

            $this->request = $request;

            $middleware = $this->getMiddleware();

            if (is_null($middleware)) {
                exception('fast', 'no middleware intercepts request');
            } elseif ($middleware instanceof MiddlewareInterface) {
                $methods = get_class_methods($middleware);

                if (in_array('process', $methods)) {
                    $this->response = callMethod($middleware, 'process', $request, $this);
                } elseif (in_array('handle', $methods)) {
                    $this->response = callMethod($middleware, 'handle', $request, $this);
                }
            } elseif (is_callable($middleware)) {
                if (is_array($middleware)) {
                    $params = array_merge($middleware, [$request, [$this, 'process']]);
                    $this->response = gi()->call(...$params);
                } elseif ($middleware instanceof Closure) {
                    $params = array_merge([$middleware], [$request, [$this, 'process']]);
                    $this->response = gi()->makeClosure(...$params);
                } else {
                    $params = array_merge([$middleware, '__invoke'], [$request, [$this, 'process']]);
                    $this->response = gi()->call(...$params);
                }
            }

            return $this->response;
        }

        /**
         * @param $middlewareClass
         *
         * @return Fast
         */
        public function addMiddleware($middlewareClass)
        {
            $this->middlewares[] = $middlewareClass;

            return $this;
        }

        public function before($concern, $middlewareClass)
        {
            $new = [];

            foreach ($this->middlewares as $middleware) {
                if ($middleware === $concern) {
                    $new[] = $middlewareClass;
                }

                $new[] = $middleware;
            }

            $this->middlewares = $new;

            return $this;
        }

        public function after($concern, $middlewareClass)
        {
            $new = [];

            foreach ($this->middlewares as $middleware) {
                $new[] = $middleware;

                if ($middleware === $concern) {
                    $new[] = $middlewareClass;
                }
            }

            $this->middlewares = $new;

            return $this;
        }

        /**
         * @return mixed|null|object
         *
         * @throws \ReflectionException
         */
        private function getMiddleware()
        {
            $middlewares    = $this->middlewares;
            $middleware     = array_shift($middlewares);

            $this->middlewares = $middlewares;

            if (is_string($middleware)) {
                return gi()->make($middleware);
            } elseif (is_callable($middleware)) {
                $middleware = call_user_func_array($middleware, [$this]);

                if (is_string($middleware)) {
                    return gi()->make($middleware);
                } else {
                    return $middleware;
                }
            } else {
                return $middleware;
            }
        }

        /**
         * @param string $moduleClass
         *
         * @return Fast
         *
         * @throws \ReflectionException
         */
        public function addModule(string $moduleClass): self
        {
            $module = gi()->make($moduleClass);

            $methods = get_class_methods($module);

            if (in_array('boot', $methods)) {
                gi()->call($module, 'boot', $this);
            }

            if (in_array('init', $methods)) {
                gi()->call($module, 'init', $this);
            }

            if (in_array('config', $methods)) {
                gi()->call($module, 'config', $this);
            }

            if (in_array('di', $methods)) {
                gi()->call($module, 'di', $this);
            }

            if (in_array('routes', $methods)) {
                gi()->call($module, 'routes', $this->router(), $this);
            }

            if (in_array('twig', $methods)) {
                gi()->call($module, 'twig', $this);
            }

            if (in_array('policies', $methods)) {
                gi()->call($module, 'policies', $this);
            }

            if (in_array('events', $methods)) {
                gi()->call($module, 'events', gi()->make(FastEvent::class));
            }

            return $this;
        }

        /**
         * @return bool
         */
        public function isDebug(): bool
        {
            return 'production' !== appenv('APPLICATION_ENV', 'production');
        }

        public function router()
        {
            if (!$this->defined('router')) {
                $router = new FastRouter;

                $this->define('router', $router);
            }

            if (!isset($this->router)) {
                $router = fo();

                $router->macro(
                    'view', function ($path, $file, $name) {
                        $instance   = $this->resolve(Fastmiddlewareview::class);
                        $middleware = [$instance, 'process'];
                        $router     = $this->router();

                        $router->addRoute('GET', $path, $middleware, $name);
                        $this->define('views.routes.' . $name, $file);

                        return $this->router();
                    }
                );

                $router->macro(
                    'redirect', function ($path, $url, $name) use ($router) {
                        $path       = '/' . trim($path, '/');
                        $instance   = $this->resolve(Fastmiddlewareredirect::class);
                        $middleware = [$instance, 'process'];

                        $router->addRoute('GET', $path, $middleware, $name);
                        $this->define('redirects.routes.' . $name, $url);

                        return $router;
                    }
                );

                $router->macro(
                    'addRoute', function ($method, $path, $next, $name = null, $middleware = null) {
                        if (is_string($next)) {
                            list($class, $action) = explode('@', $next, 2);
                            $next = [gi()->make($class), $action];
                        } elseif (is_array($next)) {
                            $class = $next[0];
                            $action = $next[1];
//
                            if (is_string($class)) {
                                $next = [gi()->make($class), $action];
                            }
                        }

                        $path = '/' . trim($path, '/');

                        if ($middleware instanceof Objet) {
                            $middleware = null;
                        }

                        if (is_array($next) && (null === $name || $name instanceof Objet)) {
                            $name = lcfirst(
                                Inflector::camelize(
                                    Inflector::lower($method) . '_' . $next[1]
                                )
                            );
                        }

                        $method = empty($method)
                        ? FastRoute::HTTP_METHOD_ANY :
                        !is_array($method) ? [Inflector::upper($method)] : $method;

                        $routes = Registry::get('fast.routes', []);
                        $key = $path . ':' . serialize($method);

                        if (!in_array($key, $routes)) {
                            /**
                             * @var $fastRouter FastRouter
                             */
                            $fastRouter = $this->defined('router');

                            $route = new FastRoute($path, $next, $method, $name);

                            $routeToSave = [
                                'path' => $path,
                                'name' => $name,
                                'method' => implode(',', $method)
                            ];

                            if (is_array($next)) {
                                $routeToSave['next'] = get_class(current($next)) . '@' . end($next);
                            } else {
                                $routeToSave['next'] = $next;
                            }

                            $routesSaved = getCore('allroutes', []);

                            $routesSaved[] = $routeToSave;

                            setCore('allroutes', $routesSaved);

                            $fastRouter->addRoute($route);

                            $routes[] = $key;

                            Registry::set('fast.routes', $routes);

                            if (null !== $middleware) {
                                pusher('routes.middlewares', [$route->getName() => $middleware]);
                            }
                        }

                        return $this->router();
                    }
                );

                $router->macro(
                    'rest', function ($name, $middleware) {
                        /**
                         * @var $fastRouter FastRouter
                         */
                        $fastRouter = $this->defined('router');

                        $fastRouter->addRoute(
                            new FastRoute('/' . $name, $middleware, ['GET'], $name . '.index')
                        );

                        $fastRouter->addRoute(
                            new FastRoute(
                                '/' . $name . '/add',
                                $middleware,
                                ['GET'],
                                $name . '.add'
                            )
                        );

                        $fastRouter->addRoute(
                            new FastRoute(
                                '/' . $name . '/create',
                                $middleware,
                                ['POST'],
                                $name . '.create'
                            )
                        );

                        $fastRouter->addRoute(
                            new FastRoute(
                                '/' . $name . "/{id:\d+}",
                                $middleware,
                                ['GET'],
                                $name . '.edit'
                            )
                        );

                        $fastRouter->addRoute(
                            new FastRoute(
                                '/' . $name . "/{id:\d+}",
                                $middleware,
                                ['PUT'],
                                $name . '.update'
                            )
                        );

                        $fastRouter->addRoute(
                            new FastRoute(
                                '/' . $name . "/{id:\d+}",
                                $middleware,
                                ['DELETE'],
                                $name . '.delete'
                            )
                        );

                        return $this->router();
                    }
                );

                $router->macro(
                    'any', function ($path, $middleware, $name) {
                        $method     = FastRoute::HTTP_METHOD_ANY;
                        /**
                         * @var $fastRouter FastRouter
                         */
                        $fastRouter = $this->defined('router');
                        $fastRouter->addRoute(
                            new FastRoute($path, $middleware, $method, $name)
                        );

                        return $this->router();
                    }
                );

                $router->macro('urlFor', function ($name, array $params = []) {
                    /**
                     * @var $fastRouter FastRouter
                     */
                    $fastRouter = $this->defined('router');

                    return $fastRouter->generateUri($name, $params);
                });

                $router->macro('self', function () use ($router) {
                    return $router;
                });

                $router->macro('match', function ($request = null) {
                    $request    = is_null($request) ? $this->getRequest() : $request;

                    /**
                     * @var $fastRouter FastRouter
                     */
                    $fastRouter = $this->defined('router');
                    $result     = $fastRouter->match($request);

                    if ($result->isSuccess()) {
                        return fo([
                            'name'          => $result->getMatchedRouteName(),
                            'middleware'    => $result->getMatchedMiddleware(),
                            'params'        => $result->getMatchedParams()
                        ]);
                    }

                    return null;
                });

                $this->router = $router;
            }

            handler('router', $this->router);

            return $this->router;
        }

        public function __call(string $method, array $args)
        {
            $fn = 'Octo\\' . $method;

            if (function_exists($fn)) {
                return call_user_func_array($fn, $args);
            }

            if (!empty($args)) {
                return $this->set(Strings::uncamelize($method), current($args));
            } else {
                return $this->app->dataget(Strings::uncamelize($method));
            }
        }

        /**
         * @param string $key
         * @param mixed $value
         *
         * @return mixed|null
         */
        public function define(string $key, $value = 'octodummy')
        {
            $keyDefine = 'fast.' . $key;

            if ('octodummy' === $value) {
                return actual($keyDefine);
            }

            return actual($keyDefine, $value);
        }

        /**
         * @param string $key
         * @param null $default
         *
         * @return mixed|null
         */
        public function defined(string $key, $default = null)
        {
            $keyDefine = 'fast.' . $key;

            $defined = actual($keyDefine);

            if (is_null($defined)) {
                $defined = $default;
            }

            return is_callable($defined) ? $defined($this) : $defined;
        }

        /**
         * @param string $key
         * @param mixed|null $default
         *
         * @return mixed|null
         */
        public function value(string $key, $default = null)
        {
            $value = $this->define($key);

            return $value ? $value : $default;
        }

        /**
         * @param string $routeName
         * @param array $params
         *
         * @return string
         */
        public function path(string $routeName, array $params): string
        {
            /**
             * @var $fastRouter FastRouter
             */
            $fastRouter = $this->defined("router");

            return $fastRouter->generateUri($routeName, $params);
        }

        /**
         * @param array $context
         *
         * @return array
         *
         * @throws TypeError
         */
        public function beforeRender($context = []): array
        {
            $session = $this->getSession();

            if (isset($session['flash'])) {
                $context['flash'] = $session['flash'];
            }

            unset($session['flash']);

            return $context;
        }

        /**
         * @return Fastcontainer
         */
        public function getApp(): Fastcontainer
        {
            return $this->app;
        }

        /**
         * @return bool
         */
        public function isInvalid(): bool
        {
            $statusCode = isset($this->response) ? $this->response->getStatusCode() : 0;

            return $statusCode < 100 || $statusCode >= 600;
        }

        /**
         * @return bool
         */
        public function isInformational(): bool
        {
            $statusCode = isset($this->response) ? $this->response->getStatusCode() : 0;

            return $statusCode >= 100 && $statusCode < 200;
        }

        /**
         * @return bool
         */
        public function isSuccessful(): bool
        {
            $statusCode = isset($this->response) ? $this->response->getStatusCode() : 0;

            return $statusCode >= 200 && $statusCode < 300;
        }

        /**
         * @return bool
         */
        public function isRedirection(): bool
        {
            $statusCode = isset($this->response) ? $this->response->getStatusCode() : 0;

            return $statusCode >= 300 && $statusCode < 400;
        }

        /**
         * @return bool
         */
        public function isClientError(): bool
        {
            $statusCode = isset($this->response) ? $this->response->getStatusCode() : 0;

            return $statusCode >= 400 && $statusCode < 500;
        }

        /**
         * @return bool
         */
        public function isServerError(): bool
        {
            $statusCode = isset($this->response) ? $this->response->getStatusCode() : 0;

            return $statusCode >= 500 && $statusCode < 600;
        }

        /**
         * @return bool
         */
        public function isOk(): bool
        {
            $statusCode = isset($this->response) ? $this->response->getStatusCode() : 0;

            return 200 === $statusCode;
        }

        /**
         * @return bool
         */
        public function isForbidden(): bool
        {
            $statusCode = isset($this->response) ? $this->response->getStatusCode() : 0;

            return 403 === $statusCode;
        }

        /**
         * @return bool
         */
        public function isNotFound(): bool
        {
            $statusCode = isset($this->response) ? $this->response->getStatusCode() : 0;

            return 404 === $statusCode;
        }

        /**
         * @return bool
         */
        public function isEmpty(): bool
        {
            $statusCode = isset($this->response) ? $this->response->getStatusCode() : 0;

            return in_array($statusCode, [204, 304]);
        }

        /**
         * @return Psr7Response
         * @throws \ReflectionException
         */
        public function getResponse(): Psr7Response
        {
            return isset($this->response) ? $this->response : $this->response();
        }

        /**
         * @param string $name
         * @return string
         * @throws \ReflectionException
         */
        public function getLang(string $name = 'lng'): string
        {
            $session        = $this->getSession();
            $language       = isAke($session, $name, null);
            $isCli          = false;
            $fromBrowser    = isAke($_SERVER, 'HTTP_ACCEPT_LANGUAGE', false);

            if (false === $fromBrowser) {
                $isCli = true;
            }

            if ($isCli) {
                return defined('DEFAULT_LANGUAGE') ? DEFAULT_LANGUAGE : 'en';
            }

            $var = defined('LANGUAGE_VAR') ? LANGUAGE_VAR : 'lng';

            if (is_null($language)) {
                $language = $this->getRequest()->getAttribute($var, \Locale::acceptFromHttp($fromBrowser));
                $session[$name] = $language;
            }

            if (fnmatch('*_*', $language)) {
                list($language, $d) = explode('_', $language, 2);
                $session[$name] = $language;
            }

            return $language;
        }

        /**
         * @param string $language
         * @param string $name
         *
         * @return Fast
         *
         * @throws TypeError
         */
        public function setLang(string $language, $name = 'lng'): self
        {
            $session = $this->getSession();

            $session[$name] = $language;

            return $this;
        }

        /**
         * @return mixed|null|FastEvent
         * @throws \ReflectionException
         */
        public function event()
        {
            return event(...func_get_args());
        }

        /**
         * @return mixed|null|FastEvent
         * @throws \ReflectionException
         */
        public function dispatch()
        {
            return event(...func_get_args());
        }

        /**
         * @param $response
         * @return Fast
         */
        public function setResponse($response): self
        {
            $this->response = $response;

            return $this;
        }
    }

    trait FastRegistryTrait
    {
        /**
         * @var string
         */
        protected $registryInstance;

        /**
         * @param string $key
         * @param $value
         *
         * @return self
         */
        public function set(string $key, $value): self
        {
            $key = $this->getRegistryKey($key);

            Registry::set($key, $value);

            return $this;
        }

        /**
         * @param string $key
         * @param null $default
         *
         * @return mixed
         */
        public function get(string $key, $default = null)
        {
            $key = $this->getRegistryKey($key);

            return Registry::get($key, $default);
        }

        /**
         * @param $key
         * @param mixed $callable

         * @return mixed
         */
        public function getOr(string $key, $callable)
        {
            if (!is_callable($callable)) {
                $callable = function () use ($callable) {return $callable;};
            }

            $res = $this->get($key, 'octodummy');

            if ('octodummy' === $res) {
                $this->set($key, $res = $callable());
            }

            return $res;
        }

        /**
         * @param string $key
         * @param mixed|null $default
         *
         * @return mixed|null
         */
        public function getOnce(string $key, $default = null)
        {
            if ($this->has($key)) {
                $value = $this->get($key);

                $this->delete($key);

                return $value;
            }

            return $default;
        }

        /**
         * @param string $key
         *
         * @return bool
         */
        public function has(string $key)
        {
            $key = $this->getRegistryKey($key);

            return 'octodummy' !== Registry::get($key, 'octodummy');
        }

        /**
         * @param string $key
         *
         * @return bool
         */
        public function delete(string $key)
        {
            if ($this->has($key)) {
                $key = $this->getRegistryKey($key);
                Registry::delete($key);

                return true;
            }

            return false;
        }

        /**
         * @param string $key
         *
         * @return string
         */
        private function getRegistryKey(string $key)
        {
            if (is_null($this->registryInstance)) {
                $this->registryInstance = hash(token() . get_called_class());
            }

            return $this->registryInstance . $key;
        }
    }

    trait FastTrait
    {
        /**
         * @param string $method
         * @param array $args
         *
         * @return mixed
         */
        public function __call(string $method, array $args)
        {
            if (fnmatch('helper*', $method) && strlen($method) > 6) {
                $method = 'Octo\\' . str_replace_first('helper', '', $method);
            } else {
                $method = 'Octo\\' . $method;
            }

            if (function_exists($method)) {
                return call_user_func_array($method, $args);
            }
        }

        /**
         * @return Fast
         * @throws \ReflectionException
         */
        public function getContainer(): Fast
        {
            return getContainer();
        }

        /**
         * @return ServerRequestInterface
         * @throws \ReflectionException
         */
        public function getRequest(): ServerRequestInterface
        {
            return getRequest();
        }

        /**
         * @param null|string $name
         * @param null|string $driver
         * @return mixed|null|Live|Session
         * @throws Exception
         * @throws \ReflectionException
         */
        public function getSession(?string $name = null, ?string $driver = null)
        {
            return getSession($name, $driver);
        }

        /**
         * @return Octalia|Orm
         */
        public function getDb()
        {
            return getDb();
        }

        /**
         * @return FastLog
         * @throws \ReflectionException
         */
        public function getLog()
        {
            return getLog();
        }

        /**
         * @return mixed|object
         * @throws \ReflectionException
         */
        public function getKh()
        {
            return getCache();
        }

        /**
         * @return mixed|object
         * @throws \ReflectionException
         */
        public function resolve()
        {
            return getContainer()->resolve(...func_get_args());
        }

        /**
         * @return mixed|object
         * @throws \ReflectionException
         */
        public function resolveOnce()
        {
            return getContainer()->resolveOnce(...func_get_args());
        }

        /**
         * @return Work
         */
        public function job()
        {
            return job(...func_get_args());
        }

        /**
         * @return FastEvent
         * @throws \ReflectionException
         */
        public function getEventManager()
        {
            return getEventManager();
        }

        /**
         * @return \PDO
         */
        public function getPdo()
        {
            return getPdo();
        }

        /**
         * @return Fastcontainer
         * @throws \ReflectionException
         */
        public function getDI()
        {
            return getContainer()->getApp();
        }

        public function dbg()
        {
            lvd(...func_get_args());
        }

        public function ddbg()
        {
            ldd(...func_get_args());
        }

        /**
         * @return Listener
         */
        public function getEvent()
        {
            return getEvent();
        }

        /**
         * @param string $lng
         *
         * @return \Faker\Generator
         */
        public function faker(string $lng = 'fr_FR')
        {
            return faker($lng);
        }

        /**
         * @param null $orm
         * @return mixed|null
         * @throws \ReflectionException
         */
        public function orm($orm = null)
        {
            return orm($orm);
        }

        /**
         * @param $value
         * @param callable|null $callback
         * @return mixed|Tap
         * @throws \ReflectionException
         */
        public function same($value, ?callable $callback = null)
        {
            return tap($value, $callback);
        }

        /**
         * @return mixed|null|object|Fast|In
         * @throws \ReflectionException
         */
        public function app()
        {
            return app(...func_get_args());
        }

        /**
         * @param null|Live $live
         * @return mixed|null
         * @throws \ReflectionException
         */
        public function live(?Live $live = null)
        {
            return live($live);
        }

        /**
         * @return \Swift_Mailer
         * @throws \ReflectionException
         */
        public function mailer(): \Swift_Mailer
        {
            return mailer();
        }

        /**
         * @param string $key
         * @param mixed $value
         *
         * @return mixed
         */
        public function registry(string $key, $value = 'octodummy')
        {
            /* Polymorphism  */
            if (is_array($key)) {
                foreach ($key as $k => $v) {
                    Registry::set($k, $v);
                }

                return true;
            }

            if ('octodummy' === $value) {
                return Registry::get($key);
            }

            Registry::set($key, $value);
        }

        /**
         * @param string $key
         * @param null $default
         *
         * @return mixed
         */
        public function registered(string $key, $default = null)
        {
            return Registry::get($key, $default);
        }

        /**
         * @param array ...$args
         * @return Instanciator
         */
        public function setInstance(...$args): Instanciator
        {
            return setInstance(...$args);
        }

        /**
         * @param array ...$args
         *
         * @return mixed
         */
        public function getInstance(...$args)
        {
            return getInstance(...$args);
        }

        /**
         * @param array ...$args
         *
         * @return bool
         */
        public function hasInstance(...$args): bool
        {
            return hasInstance(...$args);
        }

        /**
         * @param array ...$args
         */
        public function delInstance(...$args)
        {
            delInstance(...$args);
        }

        /**
         * @param array ...$args
         * @return mixed
         */
        public function oneInstance(...$args)
        {
            return instanciator()->getOr(...$args);
        }
    }

    trait Framework
    {
        use Tapable;
        use FastTrait;
        use FastRegistryTrait;
    }

    /* Interfaces */
    interface ToArray
    {
        public function toArray(): array;
    }

    interface FastListenerInterface {}
    interface FastQueueInterface {}
    interface FastModelInterface {}
    interface FastModuleInterface {}
    interface FastJobInterface
    {
        public function process();
        public function onSuccess();
        public function onFail();
    }
    interface FastOrmInterface {}
    interface FastExceptionInterface {}
    interface FastSessionInterface {}
    interface FastCacheInterface {}
    interface FastFlashInterface {}
    interface FastLogInterface {}
    interface FastRegistryInterface {}
    interface FastDbInterface {}
    interface FastMailerInterface {}
    interface FastTranslatorInterface {}
    interface FastEventManagerInterface {}
    interface FastEventInterface
    {
        public function fire();
        public function onSuccess();
        public function onFail();
    }
    interface FastViewInterface {}
    interface FastRouterInterface {}
    interface FastRouteInterface {}
    interface FastRendererInterface {}
    interface FastAuthInterface {}
    interface FastStorageInterface {}
    interface FastEventSubscriberInterface
    {
        public function getEvents(): array;
    }

    interface FastContainerInterface
    {
        public function get($key, $singleton = false);
        public function has($key);
    }

    interface FastUserOrmInterface {}
    interface FastRoleOrmInterface {}
    interface FastPermissionInterface {}

    class FastOrm implements FastOrmInterface {}
    class FastMailer implements FastMailerInterface {}
    class FastSession implements FastSessionInterface {}

    trait Extendable
    {
        protected $_mother;
        protected $_called = false;

        /**
         * @return mixed
         */
        public function handle()
        {
            $args           = func_get_args();
            $action         = array_shift($args);
            $this->_mother  = current($args);

            if (method_exists($this, 'setUp') && false === $this->_called) {
                $this->setUp(current($args));
                $this->_called = true;
            }

            return $this->{$action}(...$args);
        }

        /**
         * @param string $method
         * @param array $parameters
         *
         * @return mixed
         */
        public function __call(string $method, array $parameters)
        {
            return $this->_mother->{$method}(...$parameters);
        }
    }

    class FastAuth implements FastAuthInterface
    {
        use Extendable;

        /**
         * @return Lock
         */
        public function getAuth(): Lock
        {
            return $this->_mother;
        }
    }

    class FastGate
    {
        /**
         * @param string $key
         * @param callable $callable
         * @param bool $paste
         *
         * @throws NativeException
         */
        public static function rule(string $key, callable $callable, bool $paste = false)
        {
            $gates = Registry::get('fast.gates', []);

            if (false === $paste) {
                $rule = aget($gates, $key, false);

                if (is_callable($rule)) {
                    throw new NativeException("The rule {$key} ever exists.");
                }
            }

            $gates[$key] = $callable;

            Registry::set('fast.gates', $gates);
        }

        /**
         * @param string $key
         * @param mixed|null $default
         *
         * @return mixed|null
         */
        public static function get(string $key, $default = null)
        {
            if ($user = self::user()) {
                return dataget($user, $key, $default);
            }

            return $default;
        }

        /**
         * @return mixed|null
         */
        public static function id()
        {
            return self::get('id');
        }

        /**
         * @return mixed|null
         */
        public static function email()
        {
            return self::get('email');
        }

        public static function user()
        {
            return getContainer()->defined('user');
        }

        /**
         * @return bool
         */
        public static function isAuth(): bool
        {
            return !is_null(self::user());
        }

        /**
         * @return bool
         */
        public static function isGuest(): bool
        {
            return !self::isAuth();
        }

        /**
         * @param string $key
         *
         * @return bool
         */
        public static function has(string $key): bool
        {
            $gates  = Registry::get('fast.gates', []);
            $rule   = isAke($gates, $key, false);

            return false !== $rule;
        }

        /**
         * @return bool|mixed|null
         *
         * @throws \ReflectionException
         */
        public static function can()
        {
            if (self::isAuth()) {
                $args   = func_get_args();
                $key    = array_shift($args);
                $gates  = Registry::get('fast.gates', []);
                $rule   = isAke($gates, $key, false);

                if (is_callable($rule)) {
                    $params = array_merge([self::user()], $args);

                    if ($rule instanceof Closure) {
                        $params = array_merge([$rule], $params);

                        return instanciator()->makeClosure(...$params);
                    } else {
                        if (is_array($rule)) {
                            $params = array_merge($rule, $params);

                            return instanciator()->call(...$params);
                        } else {
                            return $rule(...$params);
                        }
                    }
                }
            }

            return false;
        }

        /**
         * @param string $key
         * @return bool
         *
         * @throws \ReflectionException
         */
        public static function cannot(string $key): bool
        {
            return !self::can(...func_get_args());
        }

        /**
         * @param string $key
         *
         * @return bool
         */
        public static function cant(string $key): bool
        {
            return self::cannot(...func_get_args());
        }

        /**
         * @param string $key
         *
         * @throws NativeException
         */
        public static function authorize(string $key)
        {
            self::rule($key, function () {
                return true;
            }, true);
        }

        /**
         * @param string $key
         *
         * @throws NativeException
         */
        public static function forbid(string $key)
        {
            self::rule($key, function () {
                return false;
            }, true);
        }

        /**
         * @return Lock
         */
        public static function getLock()
        {
            return getContainer()->defined('lock');
        }
    }

    class FastPermission implements FastPermissionInterface
    {
        use Extendable;

        /**
         * @return Permission
         */
        public function getManager(): Permission
        {
            return $this->_mother;
        }
    }

    class FastFactory
    {
        /**
         * @var Ormmodel|Entity|Octal
         */
        private $entity;

        /**
         * @var string
         */
        private $model;

        /**
         * @param string $model
         * @param callable $resolver
         *
         * @throws NativeException
         */
        public static function add(string $model, callable $resolver)
        {
            $factories = Registry::get('fast.factories', []);

            $factory = isAke($factories, $model, false);

            if (is_callable($factory)) {
                throw new NativeException("The factory $model ever exists.");
            }

            $factories[$model] = $resolver;

            Registry::set('fast.factories', $factories);
        }

        /**
         * @param string $model
         * @param FastOrmInterface|Ormmodel|Elegant|Elegantmemory|Entity|Octal $entity
         */
        public function __construct(string $model, $entity)
        {
            $this->entity   = $entity;
            $this->model    = $model;
        }

        /**
         * @param array ...$args
         *
         * @return Collection
         *
         * @throws NativeException
         */
        public function create(...$args)
        {
            $factories = Registry::get('fast.factories', []);

            $factory = isAke($factories, $this->model, false);

            if (!class_exists($this->model) || false === $factory) {
                throw new NativeException("The factory {$this->model} does not exist.");
            }

            $count  = 1;
            $params = [];
            $lng    = null;

            if (!empty($args)) {
                if (count($args) === 3) {
                    $count  = current($args);
                    $params = $args[1];
                    $lng    = end($args);
                } elseif (count($args) === 2) {
                    $count  = current($args);
                    $params = end($args);
                } elseif (count($args) === 1) {
                    $arg = current($args);

                    if (is_int($arg)) {
                        $count = $arg;
                    } elseif (is_array($arg)) {
                        $params = $arg;
                    }
                }
            }

            $results = [];

            $faker = $lng ? faker($lng) : faker();

            for ($i = 0; $i < $count; ++$i) {
                $data = $factory($faker, $this->entity);

                if ($this->entity instanceof Bank) {
                    $results[] = $this->entity->store(array_merge($data, $params));
                } else {
                    $results[] = $this->entity->create(array_merge($data, $params));
                }
            }

            return coll($results);
        }

        /**
         * @param array ...$args
         *
         * @return Collection
         *
         * @throws NativeException
         */
        public function make(...$args)
        {
            $factories = Registry::get('fast.factories', []);

            $factory = isAke($factories, $this->model, false);

            if (!class_exists($this->model) || false === $factory) {
                throw new NativeException("The factory {$this->model} does not exist.");
            }

            $count  = 1;
            $params = [];
            $lng    = null;

            if (!empty($args)) {
                if (count($args) === 3) {
                    $count  = current($args);
                    $params = $args[1];
                    $lng    = end($args);
                } elseif (count($args) === 2) {
                    $count = current($args);
                    $params = end($args);
                } elseif (count($args) === 1) {
                    $arg = current($args);

                    if (is_int($arg)) {
                        $count = $arg;
                    } elseif (is_array($arg)) {
                        $params = $arg;
                    }
                }
            }

            $results = [];

            $faker = $lng ? faker($lng) : faker();

            for ($i = 0; $i < $count; ++$i) {
                $data = $factory($faker, $this->entity);

                if (
                    $this->entity instanceof Ormmodel   ||
                    $this->entity instanceof Elegant    ||
                    $this->entity instanceof Elegantmemory) {
                    $results[] = new $this->model(array_merge($data, $params));
                } elseif ($this->entity instanceof Bank) {
                    $results[] = $this->entity->hydrator(array_merge($data, $params));
                } else {
                    $results[] = $this->entity->model(array_merge($data, $params));
                }
            }

            return coll($results);
        }
    }

    class StatusCode
    {
        const HTTP_CONTINUE = 100;
        const HTTP_SWITCHING_PROTOCOLS = 101;
        const HTTP_PROCESSING = 102;

        const HTTP_OK = 200;
        const HTTP_CREATED = 201;
        const HTTP_ACCEPTED = 202;
        const HTTP_NONAUTHORITATIVE_INFORMATION = 203;
        const HTTP_NO_CONTENT = 204;
        const HTTP_RESET_CONTENT = 205;
        const HTTP_PARTIAL_CONTENT = 206;
        const HTTP_MULTI_STATUS = 207;
        const HTTP_ALREADY_REPORTED = 208;
        const HTTP_IM_USED = 226;

        const HTTP_MULTIPLE_CHOICES = 300;
        const HTTP_MOVED_PERMANENTLY = 301;
        const HTTP_FOUND = 302;
        const HTTP_SEE_OTHER = 303;
        const HTTP_NOT_MODIFIED = 304;
        const HTTP_USE_PROXY = 305;
        const HTTP_UNUSED= 306;
        const HTTP_TEMPORARY_REDIRECT = 307;
        const HTTP_PERMANENT_REDIRECT = 308;

        const HTTP_BAD_REQUEST = 400;
        const HTTP_UNAUTHORIZED  = 401;
        const HTTP_PAYMENT_REQUIRED = 402;
        const HTTP_FORBIDDEN = 403;
        const HTTP_NOT_FOUND = 404;
        const HTTP_METHOD_NOT_ALLOWED = 405;
        const HTTP_NOT_ACCEPTABLE = 406;
        const HTTP_PROXY_AUTHENTICATION_REQUIRED = 407;
        const HTTP_REQUEST_TIMEOUT = 408;
        const HTTP_CONFLICT = 409;
        const HTTP_GONE = 410;
        const HTTP_LENGTH_REQUIRED = 411;
        const HTTP_PRECONDITION_FAILED = 412;
        const HTTP_REQUEST_ENTITY_TOO_LARGE = 413;
        const HTTP_REQUEST_URI_TOO_LONG = 414;
        const HTTP_UNSUPPORTED_MEDIA_TYPE = 415;
        const HTTP_REQUESTED_RANGE_NOT_SATISFIABLE = 416;
        const HTTP_EXPECTATION_FAILED = 417;
        const HTTP_IM_A_TEAPOT = 418;
        const HTTP_MISDIRECTED_REQUEST = 421;
        const HTTP_UNPROCESSABLE_ENTITY = 422;
        const HTTP_LOCKED = 423;
        const HTTP_FAILED_DEPENDENCY = 424;
        const HTTP_UPGRADE_REQUIRED = 426;
        const HTTP_PRECONDITION_REQUIRED = 428;
        const HTTP_TOO_MANY_REQUESTS = 429;
        const HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE = 431;
        const HTTP_CONNECTION_CLOSED_WITHOUT_RESPONSE = 444;
        const HTTP_UNAVAILABLE_FOR_LEGAL_REASONS = 451;
        const HTTP_CLIENT_CLOSED_REQUEST = 499;

        const HTTP_INTERNAL_SERVER_ERROR = 500;
        const HTTP_NOT_IMPLEMENTED = 501;
        const HTTP_BAD_GATEWAY = 502;
        const HTTP_SERVICE_UNAVAILABLE = 503;
        const HTTP_GATEWAY_TIMEOUT = 504;
        const HTTP_VERSION_NOT_SUPPORTED = 505;
        const HTTP_VARIANT_ALSO_NEGOTIATES = 506;
        const HTTP_INSUFFICIENT_STORAGE = 507;
        const HTTP_LOOP_DETECTED = 508;
        const HTTP_NOT_EXTENDED = 510;
        const HTTP_NETWORK_AUTHENTICATION_REQUIRED = 511;
        const HTTP_NETWORK_CONNECTION_TIMEOUT_ERROR = 599;
    }

    /**
     * Class FastRequest
     * @method hasHeader($header)
     * @method getHeaders()
     * @method getUri()
     * @method getMethod()
     * @method withMethod($method)
     * @method getServerParams()
     * @method getCookieParams()
     * @method withCookieParams(array $cookies)
     * @method getQueryParams()
     * @method withQueryParams(array $query)
     * @method getUploadedFiles()
     * @method withUploadedFiles(array $uploadedFiles)
     * @method getParsedBody()
     * @method withParsedBody($data)
     * @method getAttributes()
     * @method getAttribute($name, $default = null)
     * @method withAttribute($name, $value)
     * @method withoutAttribute($name)
     */
    class FastRequest
    {
        /**
         * @throws NativeException
         */
        public function __construct()
        {
            if (false === $this->can()) {
                throw new \Exception("This request is not authorized.");
            }

            $rules = $this->rules();

            if (!empty($rules)) {
                $this->validate();
            }
        }

        /**
         * @param string $method
         * @return FastRequest
         * @throws \ReflectionException
         */
        public function setMethod(string $method): self
        {
            $this->app()->setRequest($this->native()->withMethod($method));

            return $this;
        }

        /**
         * @return array
         */
        protected function rules(): array
        {
            return [];
        }

        /**
         * @return array
         */
        protected function messages(): array
        {
            return [];
        }

        /**
         * @return array
         */
        protected function customAttributes(): array
        {
            return [];
        }

        /**
         * @return bool
         */
        protected function can(): bool
        {
            return true;
        }

        /**
         * @return MessageBag
         * @throws \ReflectionException
         */
        public function validate()
        {
            $check = Facades\Validator::make(
                $this->all(),
                $this->rules(),
                $this->messages(),
                $this->customAttributes()
            );

            $errors = new MessageBag();

            if ($check->fails()) {
                $errors = $check->errors();
                viewParams('errors', $errors);
            }

            return $errors;
        }

        /**
         * @return bool
         * @throws \Illuminate\Validation\ValidationException
         * @throws \ReflectionException
         */
        public function isValid(): bool
        {
            $errors = $this->validate();

            return 0 === count($errors);
        }

        /**
         * @return ServerRequestInterface
         * @throws \ReflectionException
         */
        public function native()
        {
            return getRequest();
        }

        /**
         * @return Fast
         * @throws \ReflectionException
         */
        public function app()
        {
            return getContainer();
        }

        /**
         * @param string $key
         * @return mixed
         * @throws \ReflectionException
         */
        public function getHeader(string $key)
        {
            return $this->native()->getHeaderLine($key);
        }

        /**
         * @return mixed
         * @throws \ReflectionException
         */
        public function userAgent()
        {
            return $this->getHeader('User-Agent');
        }

        /**
         * @param ServerRequestInterface $request
         * @return ServerRequestInterface
         * @throws \ReflectionException
         */
        public function make(ServerRequestInterface $request)
        {
            $this->app()->setRequest($request);

            return $request;
        }

        /**
         * @return string
         */
        public function ip()
        {
            return ip();
        }

        /**
         * @param string $key
         * @param null $default
         * @return mixed
         * @throws Exception
         * @throws \ReflectionException
         */
        public function old(string $key, $default = null)
        {
            /** @var array $inputs */
            $inputs = $this->session()->get('old_inputs', []);

            return isAke($inputs, $key, $default);
        }

        /**
         * @return string
         * @throws \ReflectionException
         */
        public function method(): string
        {
            return $this->native()->getMethod();
        }

        /**
         * @return bool
         * @throws \ReflectionException
         */
        public function ajax(): bool
        {
            return 'XMLHttpRequest' === $this->native()->getHeaderLine('X-Requested-With');
        }

        /**
         * @return bool
         * @throws \ReflectionException
         */
        public function isSecure(): bool
        {
            return 'https' === Inflector::lower($this->native()->getUri()->getScheme());
        }

        /**
         * @return Ultimate
         * @throws Exception
         * @throws \ReflectionException
         */
        public function session()
        {
            return getSession();
        }

        /**
         * @param mixed ...$args
         * @return mixed|null
         * @throws Exception
         * @throws FastContainerException
         * @throws \ReflectionException
         */
        public function user(...$args)
        {
            return $this->session()->user(...$args);
        }

        /**
         * @return bool
         * @throws \ReflectionException
         */
        public function isJson()
        {
            return Inflector::contains($this->getHeader('CONTENT_TYPE'), ['/json', '+json']);
        }

        /**
         * @param null|string $key
         * @param null $default
         * @return array|mixed
         * @throws \ReflectionException
         */
        public function get(?string $key = null, $default = null)
        {
            $attrs = $this->native()->getQueryParams();

            return !is_null($key) ? isAke($attrs, $key, $default) : $attrs;
        }

        /**
         * @param null|string $key
         * @param null $default
         * @return array|mixed|null|object
         * @throws \ReflectionException
         */
        public function post(?string $key = null, $default = null)
        {
            $attrs = $this->native()->getParsedBody();

            return !is_null($key) ? isAke($attrs, $key, $default) : $attrs;
        }

        /**
         * @param null|string $key
         * @param null $default
         * @return array|mixed
         * @throws \ReflectionException
         */
        public function input(?string $key = null, $default = null)
        {
            $attrs = $this->all();

            return !is_null($key) ? isAke($attrs, $key, $default) : $attrs;
        }

        /**
         * @param string $key
         * @return bool
         * @throws \ReflectionException
         */
        public function has(string $key)
        {
            return notSame('octodummy', $this->input($key, 'octodummy'));
        }

        /**
         * @param null $keys
         * @return array
         * @throws \ReflectionException
         */
        public function all($keys = null): array
        {
            $req = $this->native();

            $all = $req->getQueryParams() +
                $req->getParsedBody() +
                $req->getUploadedFiles() +
                $req->getAttributes()
            ;

            if (null === $keys) {
                return $all;
            }

            $results = [];

            foreach (is_array($keys) ? $keys : func_get_args() as $key) {
                aset($results, $key, aget($all, $key));
            }

            return $results;
        }

        /**
         * @param string $key
         * @return UploadedFile|null
         * @throws \ReflectionException
         */
        public function file(string $key)
        {
            $files  = $this->native()->getUploadedFiles();

            return isAke($files, $key, null);
        }

        /**
         * @param string $key
         * @return bool
         */
        function hasFile(string $key): bool
        {
            return 'octodummy' !== isAke($_FILES, $key, 'octodummy');
        }

        /**
         * @param $keys
         * @return mixed
         * @throws \ReflectionException
         */
        public function only($keys)
        {
            $inputs = [];
            $attrs = $this->all();

            foreach (is_array($keys) ? $keys : func_get_args() as $key) {
                $inputs[$key] = isAke($attrs, $key, null);
            }

            return 1 === func_num_args() ? reset($inputs) : $inputs;
        }

        /**
         * @param $keys
         * @return array
         * @throws \ReflectionException
         */
        public function except($keys): array
        {
            $attrs = $this->all();

            foreach (is_array($keys) ? $keys : func_get_args() as $key) {
                unset($attrs[$key]);
            }

            return $attrs;
        }

        /**
         * @param int $index
         * @param null|string $default
         * @return null|string
         * @throws \ReflectionException
         */
        public function segment(int $index = 1, ?string $default = null): ?string
        {
            $idx = $index - 1;

            return aget($this->segments(), $idx, $default);
        }

        /**
         * @return array
         * @throws \ReflectionException
         */
        public function segments(): array
        {
            $segments = explode('/', $this->native()->getUri()->getPath());

            return array_values(array_filter($segments, function ($segment) {
                return $segment !== '';
            }));
        }

        /**
         * @param mixed ...$patterns
         * @return bool
         * @throws \ReflectionException
         */
        public function is(...$patterns): bool
        {
            foreach ($patterns as $pattern) {
                if (Inflector::is($pattern, rawurldecode($this->native()->getUri()->getPath()))) {
                    return true;
                }
            }

            return false;
        }

        /**
         * @param $key
         * @param null $value
         * @return FastRequest
         * @throws \ReflectionException
         */
        public function merge($key, $value = null): self
        {
            $request = $this->native();

            if (!is_array($key)) {
                $key = [$key => $value];
            }

            foreach ($key as $k => $v) {
                $request = $request->withAttribute($k, $v);
            }

            $this->app()->setRequest($request);

            return $this;
        }

        /**
         * @param string $key
         * @param $value
         * @return FastRequest
         * @throws \ReflectionException
         */
        public function set(string $key, $value): self
        {
            return $this->merge([$key => $value]);
        }

        /**
         * @param string $key
         * @return FastRequest
         * @throws \ReflectionException
         */
        public function unset(string $key): self
        {
            $request = $this->native()->withoutAttribute($key);

            $this->app()->setRequest($request);

            return $this;
        }

        /**
         * @return string
         * @throws \ReflectionException
         */
        public function url(): string
        {
            return rtrim(preg_replace('/\?.*/', '', $this->native()->getUri()->getPath()), '/');
        }

        /**
         * @return null|FastObject
         * @throws \ReflectionException
         */
        public function route(): ?FastObject
        {
            return $this->app()->define('route');
        }

        /**
         * @return null|Module
         * @throws \ReflectionException
         */
        public function module(): ?Module
        {
            return $this->app()->define('module');
        }

        /**
         * @return Component
         * @throws Exception
         * @throws \ReflectionException
         */
        public function auth() {

            return Setup::auth($this->session());
        }

        /**
         * @param string $method
         * @param array $params
         * @return mixed
         * @throws \ReflectionException
         */
        public function __call(string $method, array $params)
        {
            return $this->native()->{$method}(...$params);
        }

        /**
         * @param $key
         * @return array|mixed
         * @throws \ReflectionException
         */
        public function __get($key)
        {
            return $this->input($key);
        }

        /**
         * @param $key
         * @return bool
         * @throws \ReflectionException
         */
        public function __isset($key)
        {
            return !is_null($this->__get($key));
        }
    }

    class FastBladeCompiler extends BladeCompiler
    {
        /**
         * @param null $files
         * @param null $cachePath
         */
        public function __construct($files = null, $cachePath = null)
        {
            $this->files        = is_null($files) ? new Filesystem() : $files;
            $this->cachePath    = is_null($cachePath) ?
                appenv('CACHE_PATH', path('app') . '/storage/cache') . '/blade' :
                $cachePath
            ;
        }
    }

    class FastEvent extends Fire {}
    class FastRedis extends Cacheredis  implements FastStorageInterface {}
    class FastCache extends Cache       implements FastStorageInterface {}
    class FastNow   extends Now         implements FastStorageInterface {}

    class AuthmiddlewareException extends NativeException {}
    class FastTwigExtensions extends Twig_Extension
    {
        use Framework;
    }

    class FastRegistry implements FastRegistryInterface
    {
        use Framework;
    }

    class FastLog implements FastLogInterface
    {
        /**
         * @var string
         */
        private $path;

        /**
         * @param string|null $path
         */
        public function __construct($path = null)
        {
            if (is_null($path) || !is_writable($path)) {
                $path = getContainer()->value('DIR_CACHE', session_save_path());
            }

            $this->path = $path;
        }

        /**
         * @param string $m
         * @param array $a
         */
        public function __call(string $m, array $a)
        {
            $message    = array_shift($a);

            logFile($this->getPath(), $message, $m);
        }

        /**
         * @param string $m
         * @param array $a
         */
        public static function __callStatic(string $m, array $a)
        {
            $message = array_shift($a);

            $self = new self;

            logFile($self->getPath(), $message, $m);
        }

        /**
         * @return string
         */
        public function getPath(): string
        {
            return $this->path;
        }
    }

    class FastBag implements ArrayAccess
    {
        use Arrayable;
    }

    class FastConfig extends FastBag {}
    class FastRules extends FastBag {}

    class FastPost extends FastBag
    {
        public function __construct($data = null)
        {
            $data = is_null($data) ? $_POST : $data;

            parent::__construct($data);
        }
    }

    class FastGet extends FastBag
    {
        public function __construct($data = null)
        {
            $data = is_null($data) ? $_GET : $data;

            parent::__construct($data);
        }
    }

    class FastCookie extends FastBag
    {
        public function __construct($data = null)
        {
            $data = is_null($data) ? $_COOKIE : $data;

            parent::__construct($data);
        }
    }

    class FastFiles extends FastBag
    {
        public function __construct($data = null)
        {
            $data = is_null($data) ? $_FILES : $data;

            parent::__construct($data);
        }
    }

    class FastServer extends FastBag
    {
        public function __construct($data = null)
        {
            $data = is_null($data) ? $_SERVER : $data;

            parent::__construct($data);
        }
    }

    class FastPhpRenderer implements FastRendererInterface, FastViewInterface
    {
        use Framework;

        /**
         * @param $name
         * @param array $context
         * @return mixed
         * @throws \ReflectionException
         */
        public function render($name, array $context = [])
        {
            if (!File::exists($name)) {
                $viewPath = $this->getContainer()->define('view.path');

                if (is_null($viewPath)) {
                    $this->exception('FastPhpRenderer', 'Please provide a view path.');
                }

                $file = $viewPath . DS . $name . '.phtml';
            } else {
                $file = $name;
            }

            return $this->vue($file, $context)->inline();
        }

        /**
         * @param string $key
         * @param mixed $value
         *
         * @return $this
         */
        public function addGlobal($key, $value)
        {
            $data = Registry::get('core.globals.view', []);

            $data[$key] = $value;

            Registry::set('core.globals.view', $data);

            return $this;
        }

        /**
         * @param null $key
         * @param null $default
         *
         * @return array|mixed|null
         */
        public function flash($key = null, $default = null)
        {
            /** @var Flash $flash */
            $flash = $this->getContainer()->resolve(Flash::class);

            if (null !== $key) {
                return $flash->get($key, $default);
            }

            return $flash->all();
        }

        /**
         * @param null|string $key
         *
         * @return mixed
         *
         * @throws TypeError
         */
        public function user(?string $key = null)
        {
            /** @var Lock $lock */
            $lock = $this->getContainer()->defined('lock');

            return $lock->get($key);
        }

        /**
         * @return bool
         *
         * @throws TypeError
         */
        public function can()
        {
            /** @var Permission $permission */
            $permission = $this->getContainer()->defined('permission');

            return $permission->can(...func_get_args());
        }
    }

    class FastTwigRenderer
        extends Twig_Environment
        implements FastRendererInterface, FastViewInterface {
        use Framework;

        /**
         * @param string $name
         * @param array $context
         *
         * @return string
         *
         * @throws \Twig_Error_Loader         *
         * @throws \Twig_Error_Runtime         *
         * @throws \Twig_Error_Syntax
         */
        public function render($name, array $context = [])
        {
            return parent::render($name . '.twig', $context);
        }
    }

    class FastMiddleware implements MiddlewareInterface
    {
        use Framework;

        /**
         * @param ServerRequestInterface $request
         * @param DelegateInterface $next
         *
         * @return ResponseInterface
         */
        public function process(ServerRequestInterface $request, DelegateInterface $next)
        {
            return $next->process($request);
        }
    }

    class Redirector extends Facade
    {
        public static function getNativeClass(): string
        {
            return FastRedirector::class;
        }
    }

    class FastRedirector
    {
        /**
         * @var FastRouter
         */
        private $router;

        public function __construct()
        {
            $this->router = getRouter();
        }

        /**
         * @return \GuzzleHttp\Psr7\MessageTrait|FastRedirector
         * @throws \ReflectionException
         */
        public function home()
        {
            return $this->route('home');
        }

        /**
         * @param string $name
         * @param array $args
         * @param int $status
         * @return \GuzzleHttp\Psr7\MessageTrait
         * @throws \ReflectionException
         */
        public function route(string $name, array $args = [], $status = 302)
        {
            return $this->to($this->router->generateUri($name, $args), $status);
        }

        /**
         * @param int $status
         * @return \GuzzleHttp\Psr7\MessageTrait
         * @throws \ReflectionException
         */
        public function refresh($status = 302)
        {
            return $this->to(getRequest()->getUri(), $status);
        }

        /**
         * @param string $path
         * @param int $status
         * @return \GuzzleHttp\Psr7\MessageTrait|FastRedirector
         * @throws \ReflectionException
         */
        public function away(string $path, $status = 302)
        {
            return $this->to($path, $status);
        }

        /**
         * @param $path
         * @param int $status
         * @return \GuzzleHttp\Psr7\MessageTrait
         * @throws \ReflectionException
         */
        public function to($path, $status = 302)
        {
            return getContainer()
                ->response()
                ->withStatus($status)
                ->withHeader('Location', $path)
            ;
        }

        /**
         * @param int $status
         * @return \GuzzleHttp\Psr7\MessageTrait|FastRedirector
         * @throws Exception
         * @throws \ReflectionException
         */
        public function back($status = 302)
        {
            return $this->to(getReferer(), $status);
        }

        /**
         * @param array $parameters
         * @return FastRedirector
         * @throws Exception
         * @throws \ReflectionException
         */
        public function with(array $parameters): self
        {
            $vars = viewParams();

            foreach ($parameters as $key => $value) {
                $vars[$key] = $value;
            }

            return $this;
        }

        /**
         * @param array $parameters
         * @param int $status
         * @return \GuzzleHttp\Psr7\MessageTrait
         * @throws Exception
         * @throws \ReflectionException
         */
        public function backWith(array $parameters, $status = 302)
        {
            return $this->with($parameters)->back($status);
        }

        /**
         * @param array $args
         * @param int $status
         * @return \GuzzleHttp\Psr7\MessageTrait
         * @throws \ReflectionException
         */
        public function current(array $args = [], $status = 302)
        {
            $route = getContainer()->define('route');

            $name = $route ? $route->name : 'home';

            return $this->route($name, $args, $status);
        }
    }

    class FastBladeDirectives
    {
        /**
         * @throws \ReflectionException
         */
        public static function register()
        {
            /** @var FastTwigExtension $twig */
            $twig = gi()->make(FastTwigExtension::class);

            bladeDirective('isnull', function ($expression) {
                return "<?php if (is_null({$expression})): ?>";
            });

            bladeDirective('endisnull', function () {
                return "<?php endif; ?>";
            });

            bladeDirective('dd', function (...$args) {
                return "<?php dd({...$args}); ?>";
            });

            bladeDirective('continue', function () {
                return "<?php continue; ?>";
            });

            bladeDirective('break', function () {
                return "<?php break; ?>";
            });

            bladeDirective('ifempty', function($expression) {
                return "<?php if(empty($expression)): ?>";
            });

            bladeDirective('endifempty', function () {
                return '<?php endif; ?>';
            });

            bladeDirective('asset', function (string $path) {
                $asset = assets_path($path);

                return "<?php echo e({$asset}); ?>";
            });

            bladeDirective('js', function (string $path) {
                $asset = assets_path($path) . '.js';

                return "<?php echo e({$asset}); ?>";
            });

            bladeDirective('path', function ($expression) use ($twig) {
                $args = explode(', ', $expression);
                $name = array_shift($args);
                $path = $twig->path($name, $args);

                return "<?php echo e({$path}); ?>";
            });

            bladeDirective('flash', function ($expression) use ($twig) {
                $args       = explode(',', preg_replace("/[\(\)\\\"\']/", '', $expression));
                $key        = array_shift($args);
                $default    = array_shift($args);
                $flash      = $twig->flash($key, $default);

                return "<?php echo e({$flash}); ?>";
            });

            bladeDirective('mix', function () {
                $mix = mix(...func_get_args());

                return "<?php echo e({$mix}); ?>";
            });

            bladeDirective('csrf_form', function () {
                $csrf = csrf();

                return "<?php echo e({$csrf}); ?>";
            });

            bladeDirective('csrf_value', function () {
                $csrf = csrf_make();

                return "<?php echo e({$csrf}); ?>";
            });

            bladeDirective('field', function (
                string $key,
                $value,
                ?string $label = null,
                array $options = [],
                array $attributes = []
            ) use ($twig) {
                $context = getCore('blade.context', []);
                $field = $twig->field($context, $key, $value, $label, $options, $attributes);

                return "<?php echo {$field}; ?>";
            });

            bladeDirective('submit', function ($expression) {
                $value = empty($expression) ? 'OK' : $expression;
                $submit = csrf() . "\n" . "<button class=\"btn btn-primary\">{$value}</button>";

                return "<?php echo {$submit}; ?>";
            });

            bladeDirective('paginate', function ($expression) use ($twig) {
                $args = explode(',', preg_replace("/[\(\)\\\"\']/", '', $expression));
                $paginatedResults = array_shift($args);
                $route = array_shift($args);
                $html = $twig->paginate($paginatedResults, $route, $args);

                return "<?php echo {$html}; ?>";
            });

            bladeDirective('lng', function ($expression) use ($twig) {
                $args   = explode(',', preg_replace("/[\(\)\\\"\']/", '', $expression));
                $key    = array_shift($args);
                $trad   = $twig->lang($key, $args);

                return echoInDirective($trad);
            });
        }
    }

    class FastTwigExtension extends FastTwigExtensions
    {
        /**
         * @return array|\Twig_Function[]
         */
        public function getFunctions()
        {
            return [
                new Twig_SimpleFunction('dump', [$this, 'dump'], ['is_safe' => ['html']]),
                new Twig_SimpleFunction('old', [$this, 'old'], ['is_safe' => ['html']]),
                new Twig_SimpleFunction('paginate', [$this, 'paginate'], ['is_safe' => ['html']]),
                new Twig_SimpleFunction('asset', [$this, 'asset']),
                new Twig_SimpleFunction('path', [$this, 'path']),
                new Twig_SimpleFunction('is_subpath', [$this, 'isSubpath']),
                new Twig_SimpleFunction('flash', [$this, 'flash']),
                new Twig_SimpleFunction('logout', [$this, 'logout']),
                new Twig_SimpleFunction('login', [$this, 'login']),
                new Twig_SimpleFunction('user', [$this, 'user']),
                new Twig_SimpleFunction('mix', [$this, 'mix'], ['is_safe' => ['html']]),
                new Twig_SimpleFunction('lang', [$this, 'lang'], ['is_safe' => ['html']]),
                new Twig_SimpleFunction('submit', [$this, 'submit'], ['is_safe' => ['html']]),
                new Twig_SimpleFunction('input_csrf', [$this, 'csrf'], ['is_safe' => ['html']]),
                new Twig_SimpleFunction('field', [$this, 'field'], [
                    'is_safe'       => ['html'],
                    'needs_context' => true
                ]),
            ];
        }

        public function paginate(Pagerfanta $paginatedResults, string $route, array $queryArgs = []): string
        {
            $view = new TwitterBootstrap4View;

            return $view->render($paginatedResults, function (int $page) use ($route, $queryArgs) {
                /**
                 * @var $fastRouter FastRouter
                 */
                $fastRouter = $this->getContainer()->define("router");

                if ($page < 1) {
                    $queryArgs['p'] = 1;
                }

                return $fastRouter->generateUri($route, [], $queryArgs);
            });
        }

        /**
         * @return string
         *
         * @throws NativeException
         */
        public function mix()
        {
            return mix(...func_get_args());
        }

        /**
         * @param string $key
         * @param null $default
         * @return mixed
         * @throws \ReflectionException
         */
        public function old(string $key, $default = null)
        {
            /** @var array $inputs */
            $inputs = $this->getContainer()->getSession()->get('old_inputs', []);

            return isAke($inputs, $key, $default);
        }

        /**
         * @param string $key
         * @param array $parameters
         * @return array|null|string
         * @throws \ReflectionException
         */
        public function lang(string $key, array $parameters)
        {
            $locale = in('locale');

            $t = setTranslator(lang_path(), $locale);

            return $t->get($key, $parameters);
        }

        /**
         * @param null|string $key
         * @param null $default
         *
         * @return array|mixed|null
         *
         * @throws \ReflectionException
         */
        public function flash(?string $key = null, $default = null)
        {
            /** @var Flash $flash */
            $flash = $this->getContainer()->resolve(Flash::class);

            if (null !== $key) {
                return $flash->get($key, $default);
            }

            return $flash->all();
        }

        /**
         * @param null|string $key
         * @return mixed|null
         * @throws \ReflectionException
         */
        public function user(?string $key = null)
        {
            /** @var Trust $trust */
            $trust = $this->getContainer()->defined('trust');

            return $trust->user($key);
        }

        /**
         * @return bool
         * @throws NativeException
         * @throws \ReflectionException
         */
        public function can()
        {
            /** @var Trust $trust */
            $trust = $this->getContainer()->defined('trust');

            return $trust->can(...func_get_args());
        }

        /**
         * @return array|Twig_Filter[]
         */
        public function getFilters()
        {
            return [
                new Twig_Filter('camelize', ['Octo\Inflector', 'camelize']),
                new Twig_Filter('uncamelize', ['Octo\Inflector', 'uncamelize']),
                new Twig_Filter('strlen', ['Octo\Inflector', 'length']),
            ];
        }

        /**
         * @param string $asset
         * @return string
         * @throws \ReflectionException
         */
        public function asset(string $asset): string
        {
            return assets_path($asset);
        }

        /**
         * @param string $routeName
         * @param array $params
         * @return string
         * @throws \ReflectionException
         */
        public function path(string $routeName, array $params = []): string
        {
            /**
             * @var $fastRouter FastRouter
             */
            $fastRouter = $this->getContainer()->define("router");

            try {
                return $fastRouter->generateUri($routeName, $params);
            } catch (\Exception $e) {
                return '/';
            }
        }

        /**
         * @param string $path
         * @param array $params
         * @return bool
         * @throws \ReflectionException
         */
        public function isSubpath(string $path, array $params = []): bool
        {
            /**
             * @var $fastRouter FastRouter
             */
            $fastRouter = $this->getContainer()->define("router");
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            $expectedUri = $fastRouter->generateUri($path, $params);

            return strpos($uri, $expectedUri) !== false;
        }

        /**
         * @return string
         * @throws \ReflectionException
         */
        public function logout()
        {
            /**
             * @var $fastRouter FastRouter
             */
            $fastRouter = $this->getContainer()->define("router");

            return $fastRouter->generateUri('logout');
        }

        /**
         * @return string
         * @throws \ReflectionException
         */
        public function login()
        {
            /**
             * @var $fastRouter FastRouter
             */
            $fastRouter = $this->getContainer()->define("router");

            return $fastRouter->generateUri('login');
        }

        /**
         * @return string
         * @throws NativeException
         */
        public function csrf()
        {
            return csrf();
        }

        /**
         * @return bool|string
         */
        public function dump()
        {
            $dump = fopen('php://memory', 'r+b');

            $this->dbg(...func_get_args());

            return stream_get_contents($dump, -1, 0);
        }

        /**
         * @param string $value
         * @return string
         * @throws NativeException
         */
        public function submit(string $value = 'OK')
        {
            return $this->csrf() . "\n" . "<button class=\"btn btn-primary\">{$value}</button>";
        }

        /**
         * @param array $context
         * @param string $key
         * @param $value
         * @param null|string $label
         * @param array $options
         * @param array $attributes
         *
         * @return string
         */
        public function field(
            array $context,
            string $key,
            $value,
            ?string $label = null,
            array $options = [],
            array $attributes = []
        ): string {
            $type = $options['type'] ?? 'text';
            $error = $this->getErrorHtml($context, $key);
            $class = 'form-group';
            $value = $this->convertValue($value);

            $attributes = array_merge([
                'class' => trim('form-control ' . ($options['class'] ?? '')),
                'name'  => $key,
                'id'    => $key
            ], $attributes);

            if ($error) {
                $class .= ' has-danger';
                $attributes['class'] .= ' form-control-danger';
            }

            if ($type === 'textarea') {
                $input = $this->textarea($value, $attributes);
            } elseif ($type === 'password') {
                $attributes['type'] = 'password';
                $input = $this->input($value, $attributes);
            } elseif ($type === 'date') {
                $attributes['type'] = 'date';
                $input = $this->input($value, $attributes);
            } elseif ($type === 'email') {
                $attributes['type'] = 'email';
                $input = $this->input($value, $attributes);
            } elseif ($type === 'time') {
                $attributes['type'] = 'time';
                $input = $this->input($value, $attributes);
            } elseif ($type === 'file' || $type === 'image') {
                $attributes['type'] = 'file';
                $input = $this->input(null, $attributes);
            } elseif ($type === 'checkbox') {
                $input = $this->checkbox($value, $attributes);
            } elseif (array_key_exists('options', $options)) {
                $input = $this->select($value, $options['options'], $attributes);
            } else {
                $attributes['type'] = $options['type'] ?? 'text';
                $input = $this->input($value, $attributes);
            }

            return "<div class=\"" . $class . "\">
              <label for=\"" . $key . "\">{$label}</label>
              {$input}
              {$error}
            </div>";
        }

        /**
         * @param $value
         * @return string
         */
        private function convertValue($value): string
        {
            if ($value instanceof \DateTime) {
                return $value->format('Y-m-d H:i:s');
            }
            return (string) $value;
        }

        /**
         * @param array $context
         * @param string $key
         * @return string
         */
        private function getErrorHtml(array $context, string $key)
        {
            $error = $context['errors'][$key] ?? false;

            if ($error) {
                return "<small class=\"form-text text-muted\">{$error}</small>";
            }

            return "";
        }

        /**
         * @param null|string $value
         * @param array $attributes
         * @return string
         */
        private function input(?string $value, array $attributes): string
        {
            return "<input " . $this->getHtmlFromArray($attributes) . " value=\"{$value}\">";
        }

        /**
         * @param null|string $value
         * @param array $attributes
         * @return string
         */
        private function checkbox(?string $value, array $attributes): string
        {
            $html = '<input type="hidden" name="' . $attributes['name'] . '" value="0"/>';

            if ($value) {
                $attributes['checked'] = true;
            }

            return $html . "<input type=\"checkbox\" " . $this->getHtmlFromArray($attributes) . " value=\"1\">";
        }

        /**
         * @param null|string $value
         * @param array $attributes
         * @return string
         */
        private function textarea(?string $value, array $attributes): string
        {
            return "<textarea " . $this->getHtmlFromArray($attributes) . ">{$value}</textarea>";
        }

        /**
         * @param null|string $value
         * @param array $options
         * @param array $attributes
         * @return string
         */
        private function select(?string $value, array $options, array $attributes)
        {
            $htmlOptions = array_reduce(
                array_keys($options), function (string $html, string $key) use ($options, $value) {
                    $params = ['value' => $key, 'selected' => $key === $value];

                    return $html . '<option ' . $this->getHtmlFromArray($params) . '>' . $options[$key] . '</option>';
                }, ""
            );
            return "<select " . $this->getHtmlFromArray($attributes) . ">$htmlOptions</select>";
        }

        /**
         * @param array $attributes
         * @return string
         */
        private function getHtmlFromArray(array $attributes)
        {
            $htmlParts = [];
            foreach ($attributes as $key => $value) {
                if ($value === true) {
                    $htmlParts[] = (string) $key;
                } elseif ($value !== false) {
                    $htmlParts[] = "$key=\"$value\"";
                }
            }

            return implode(' ', $htmlParts);
        }
    }

    class FastException extends NativeException implements FastExceptionInterface {}

    class Processor implements DelegateInterface
    {
        /**
         * @param ServerRequestInterface $request
         *
         * @return mixed|null|ResponseInterface
         *
         * @throws \ReflectionException
         */
        public function process(ServerRequestInterface $request)
        {
            return process($request);
        }
    }

    class FastResponse
    {
        /**
         * @return Fast
         * @throws \ReflectionException
         */
        public function app()
        {
            return getContainer();
        }

        /**
         * @return Psr7Response
         * @throws \ReflectionException
         */
        public function native()
        {
            return $this->app()->getResponse();
        }

        /**
         * @param $data
         * @param null $status
         * @param int $encodingOptions
         * @return \Psr\Http\Message\ResponseInterface
         * @throws \ReflectionException
         */
        public function json($data, $status = null, int $encodingOptions = 0)
        {
            /** @var \Psr\Http\Message\ResponseInterface $response */
            $response = $this->native()->withBody(new Stream(fopen('php://temp', 'r+')));
            $response->getBody()->write($json = json_encode($data, $encodingOptions));

            if ($json === false) {
                throw new \RuntimeException(json_last_error_msg(), json_last_error());
            }

            $responseWithJson = $response->withHeader('Content-Type', 'application/json;charset=utf-8');

            if (isset($status)) {
                return $responseWithJson->withStatus($status);
            }

            return $responseWithJson;
        }

        /**
         * @param $url
         * @param null $status
         * @return ResponseInterface
         * @throws \ReflectionException
         */
        public function redirect($url, $status = null)
        {
            /** @var \Psr\Http\Message\ResponseInterface $responseWithRedirect */
            $responseWithRedirect = $this->native()->withHeader('Location', (string)$url);

            if (is_null($status) && $this->native()->getStatusCode() === StatusCode::HTTP_OK) {
                $status = StatusCode::HTTP_FOUND;
            }

            if (!is_null($status)) {
                return $responseWithRedirect->withStatus($status);
            }

            return $responseWithRedirect;
        }

        /**
         * @return bool
         * @throws \ReflectionException
         */
        public function isEmpty()
        {
            return in_array(
                $this->native()->getStatusCode(),
                [StatusCode::HTTP_NO_CONTENT, StatusCode::HTTP_RESET_CONTENT, StatusCode::HTTP_NOT_MODIFIED]
            );
        }

        /**
         * @return bool
         * @throws \ReflectionException
         */
        public function isInformational()
        {
            return $this->native()->getStatusCode() >= StatusCode::HTTP_CONTINUE &&
                $this->native()->getStatusCode() < StatusCode::HTTP_OK;
        }

        /**
         * @return bool
         * @throws \ReflectionException
         */
        public function isOk()
        {
            return $this->native()->getStatusCode() === StatusCode::HTTP_OK;
        }

        /**
         * @return bool
         * @throws \ReflectionException
         */
        public function isSuccessful()
        {
            return $this->native()->getStatusCode() >= StatusCode::HTTP_OK &&
                $this->native()->getStatusCode() < StatusCode::HTTP_MULTIPLE_CHOICES;
        }

        /**
         * @return bool
         * @throws \ReflectionException
         */
        public function isRedirect()
        {
            return in_array(
                $this->native()->getStatusCode(),
                [
                    StatusCode::HTTP_MOVED_PERMANENTLY,
                    StatusCode::HTTP_FOUND,
                    StatusCode::HTTP_SEE_OTHER,
                    StatusCode::HTTP_TEMPORARY_REDIRECT
                ]
            );
        }

        /**
         * @return bool
         * @throws \ReflectionException
         */
        public function isRedirection()
        {
            return $this->native()->getStatusCode() >= StatusCode::HTTP_MULTIPLE_CHOICES &&
                $this->native()->getStatusCode() < StatusCode::HTTP_BAD_REQUEST;
        }

        /**
         * @return bool
         * @throws \ReflectionException
         */
        public function isForbidden()
        {
            return $this->native()->getStatusCode() === StatusCode::HTTP_FORBIDDEN;
        }

        /**
         * @return bool
         * @throws \ReflectionException
         */
        public function isNotFound()
        {
            return $this->native()->getStatusCode() === StatusCode::HTTP_NOT_FOUND;
        }

        /**
         * @return bool
         * @throws \ReflectionException
         */
        public function isClientError()
        {
            return $this->native()->getStatusCode() >= StatusCode::HTTP_BAD_REQUEST &&
                $this->native()->getStatusCode() < StatusCode::HTTP_INTERNAL_SERVER_ERROR;
        }

        /**
         * @return bool
         * @throws \ReflectionException
         */
        public function isServerError()
        {
            return $this->native()->getStatusCode() >= StatusCode::HTTP_INTERNAL_SERVER_ERROR &&
                $this->native()->getStatusCode() < 600;
        }

        /**
         * @return string
         * @throws \ReflectionException
         */
        public function __toString()
        {
            $output = sprintf(
                'HTTP/%s %s %s',
                $this->native()->getProtocolVersion(),
                $this->native()->getStatusCode(),
                $this->native()->getReasonPhrase()
            );

            $output .= "\r\n";

            foreach ($this->native()->getHeaders() as $name => $values) {
                $output .= sprintf('%s: %s', $name, $this->native()->getHeaderLine($name)) . Response::EOL;
            }

            $output .= "\r\n";

            $output .= (string) $this->native()->getBody();

            return $output;
        }

        /**
         * @param string $method
         * @param array $params
         * @return mixed
         * @throws \ReflectionException
         */
        public function __call(string $method, array $params)
        {
            return $this->native()->{$method}(...$params);
        }
    }

    class FastContainerException extends \Exception implements ContainerExceptionInterface {}

    class FastObject
    extends Objet
    implements
    FastUserOrmInterface,
    FastRouterInterface,
    FastRouteInterface,
    FastRoleOrmInterface {}
