<?php
    namespace Octo;

    use ArrayAccess;
    use ArrayObject;
    use Closure;
    use Exception as NativeException;
    use GuzzleHttp\Psr7\Response as Psr7Response;
    use GuzzleHttp\Psr7\ServerRequest as Psr7Request;
    use Illuminate\Filesystem\Filesystem;
    use Illuminate\View\Compilers\BladeCompiler;
    use Interop\Http\ServerMiddleware\DelegateInterface;
    use Interop\Http\ServerMiddleware\MiddlewareInterface;
    use Psr\Container\ContainerExceptionInterface;
    use Psr\Container\ContainerInterface;
    use Psr\Container\NotFoundExceptionInterface;
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

    class Fast extends ArrayObject implements
        FastContainerInterface,
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

        public function __construct()
        {
            $this->app = instanciator()->singleton(Fastcontainer::class);

            $this->request = $this->fromGlobals();

            actual('fast', $this);
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
         * @return mixed
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
         * @return Session|Live
         *
         * @throws TypeError
         */
        public function getSession()
        {
            $session = $this->define('session');

            if (is_null($session)) {
                return null;
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
                            instanciator()->singleton($extension)
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

        public function get($concern, $sinleton = null)
        {
            return $this->app->get($concern, $sinleton);
        }

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

        public function emit()
        {
            $args   = func_get_args();
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
            return instanciator()->call(...$args);
        }

        /**
         * @return mixed|object
         *
         * @throws \ReflectionException
         */
        public function resolve()
        {
            return instanciator()->factory(...func_get_args());
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
            return instanciator()->getOr($driver);
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
            return instanciator()->singleton(...$args);
        }

        /**
         * @return Object
         */
        public function getContainer()
        {
            return $this->app;
        }

        /**
         * @param array ...$args
         * @return Psr7Response
         */
        public function response(...$args)
        {
            return call_user_func_array(
                'Octo\foundry',
                array_merge([Psr7Response::class], $args)
            );
        }

        /**
         * @param int $code
         * @param string $message
         *
         * @return Psr7Response
         */
        public function abort($code = 403, $message = 'Forbidden')
        {
            return $this->response($code, [], $message);
        }

        /**
         * @param $uri
         *
         * @return Psr7Response
         */
        public function redirectResponse($uri)
        {
            $this->response = $this->response()
                ->withStatus(301)
                ->withHeader('Location', $uri)
            ;

            return $this->response;
        }

        /**
         * @param string $route
         * @param array $params
         *
         * @return Psr7Response
         */
        public function redirectRouteResponse(string $route, array $params = [])
        {
            $uri = $this->router()->urlFor($route, $params);

            return $this->redirectResponse($uri);
        }

        /**
         * @param int $status
         *
         * @return Psr7Response
         */
        public function setStatus(int $status)
        {
            $this->response = $this->response()->withStatus($status);

            return $this->response;
        }

        /**
         * @param string $key
         * @param mixed $value
         *
         * @return Psr7Response
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
                }

                if (in_array('handle', $methods)) {
                    $this->response = callMethod($middleware, 'handle', $request, $this);
                }
            } elseif (is_callable($middleware)) {
                $this->response = call_user_func_array($middleware, [$request, [$this, 'process']]);
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
                return instanciator()->singleton($middleware);
            } elseif (is_callable($middleware)) {
                $middleware = call_user_func_array($middleware, [$this]);

                if (is_string($middleware)) {
                    return instanciator()->singleton($middleware);
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
            $module = instanciator()->singleton($moduleClass);

            $methods = get_class_methods($module);

            if (in_array('boot', $methods)) {
                callMethod($module, 'boot', $this);
            }

            if (in_array('init', $methods)) {
                callMethod($module, 'init', $this);
            }

            if (in_array('config', $methods)) {
                callMethod($module, 'config', $this);
            }

            if (in_array('di', $methods)) {
                callMethod($module, 'di', $this);
            }

            if (in_array('routes', $methods)) {
                callMethod($module, 'routes', $this->router(), $this);
            }

            if (in_array('twig', $methods)) {
                callMethod($module, 'twig', $this);
            }

            if (in_array('policies', $methods)) {
                callMethod($module, 'policies', $this);
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
                if (!$this->isDebug()) {
                    $cachePath = appenv('CACHE_PATH', path('app') . '/storage/cache') . '/router';

                    $router = new FastRouter(null, null, [
                        FastRouter::CONFIG_CACHE_ENABLED => true,
                        FastRouter::CONFIG_CACHE_FILE => $cachePath
                    ]);
                } else {
                    $router = new FastRouter;
                }

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
                    'redirect', function ($path, $url, $name) {
                        $instance   = $this->resolve(Fastmiddlewareredirect::class);
                        $middleware = [$instance, 'process'];
                        $router     = $this->router();

                        $router->addRoute('GET', $path, $middleware, $name);
                        $this->define('redirects.routes.' . $name, $url);

                        return $this->router();
                    }
                );

                $router->macro(
                    'addRoute', function ($method, $path, $middleware, $name = null) {
                        if (is_array($middleware) && is_object($name)) {
                            $name = $middleware[1];
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

                            $fastRouter->addRoute(
                                new FastRoute($path, $middleware, $method, $name)
                            );

                            $routes[] = $key;

                            Registry::set('fast.routes', $routes);
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
         */
        public function getResponse(): Psr7Response
        {
            return isset($this->response) ? $this->response : $this->response();
        }

        /**
         * @param string $name
         *
         * @return string
         *
         * @throws TypeError
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
         */
        public function getContainer(): Fast
        {
            return getContainer();
        }

        /**
         * @return ServerRequestInterface
         */
        public function getRequest(): ServerRequestInterface
        {
            return getRequest();
        }

        /**
         * @return Session
         */
        public function getSession()
        {
            return getSession();
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
         * @return Cache
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
         *
         * @return FastOrmInterface
         */
        public function orm($orm = null)
        {
            return orm($orm);
        }

        /**
         * @param mixed $value
         * @param callable|null $callback
         *
         * @return mixed|Tap
         */
        public function same($value, ?callable $callback = null)
        {
            return tap($value, $callback);
        }

        /**
         * @return mixed|object|Fast
         */
        public function app()
        {
            return app(...func_get_args());
        }

        /**
         * @param null|Live $live
         *
         * @return null|Live
         */
        public function live(?Live $live = null)
        {
            return live($live);
        }

        /**
         * @return \Swift_Mailer
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
    interface FastListenerInterface {}
    interface FastQueueInterface {}
    interface FastModelInterface {}
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

    /**
     * Class FastRequest
     * @method hasHeader($header)
     * @method getHeader($header)
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
         * @param string $method
         *
         * @return FastRequest
         */
        public function setMethod(string $method): self
        {
            $request = getRequest()->withMethod($method);

            getContainer()->setRequest($request);

            return $this;
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
         *
         * @return mixed
         *
         * @throws TypeError
         */
        public function old(string $key, $default = null)
        {
            /** @var array $inputs */
            $inputs = getSession()->get('old_inputs', []);

            return isAke($inputs, $key, $default);
        }

        /**
         * @param callable $callable
         *
         * @return Checking
         */
        public function validate(callable $callable)
        {
            return $callable($this);
        }

        /**
         * @return string
         */
        public function method(): string
        {
            return getRequest()->getMethod();
        }

        /**
         * @return bool
         */
        public function isSecure(): bool
        {
            return 'https' === Inflector::lower(getRequest()->getUri()->getScheme());
        }

        /**
         * @param null|string $key
         * @param null $default
         *
         * @return array|mixed
         */
        public function get(?string $key = null, $default = null)
        {
            $attrs = getRequest()->getQueryParams();

            return !is_null($key) ? isAke($attrs, $key, $default) : $attrs;
        }

        /**
         * @param null|string $key
         * @param null|mixed $default
         *
         * @return null|mixed
         */
        public function post(?string $key = null, $default = null)
        {
            $attrs = getRequest()->getParsedBody();

            return !is_null($key) ? isAke($attrs, $key, $default) : $attrs;
        }

        /**
         * @param null|string $key
         * @param null|mixed $default
         *
         * @return null|mixed
         */
        public function input(?string $key = null, $default = null)
        {
            $attrs = $this->all();

            return !is_null($key) ? isAke($attrs, $key, $default) : $attrs;
        }

        /**
         * @return array
         */
        public function all(): array
        {
            $get    = getRequest()->getQueryParams();
            $post   = getRequest()->getParsedBody();
            $files  = getRequest()->getUploadedFiles();

            return array_merge($get, $post, $files);
        }

        /**
         * @return array
         */
        public function only(...$keys): array
        {
            $inputs = [];
            $attrs = $this->all();

            foreach ($keys as $key) {
                $inputs[$key] = isAke($attrs, $key, null);
            }

            return $inputs;
        }

        /**
         * @return array
         */
        public function except(...$keys): array
        {
            $attrs = $this->all();

            foreach ($keys as $key) {
                unset($attrs[$key]);
            }

            return $attrs;
        }

        /**
         * @param int $index
         * @param null|string $default
         *
         * @return null|string
         */
        public function segment(int $index = 1, ?string $default = null): ?string
        {
            $idx = $index - 1;

            return aget($this->segments(), $idx, $default);
        }

        /**
         * @return array
         */
        public function segments(): array
        {
            $uri = getRequest()->getUri();
            $segments = explode('/', $uri->getPath());

            return array_values(array_filter($segments, function ($segment) {
                return $segment !== '';
            }));
        }

        /**
         * @param string $method
         * @param array $params
         *
         * @return mixed
         */
        public function __call(string $method, array $params)
        {
            return getRequest()->{$method}(...$params);
        }

        /**
         * @param $key
         * @return mixed|null
         */
        public function __get($key)
        {
            return $this->input($key);
        }

        /**
         * @param $key
         * @return bool
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
         * @param string $name
         * @param array $context
         *
         * @return string
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
         * @return \GuzzleHttp\Psr7\MessageTrait
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
         */
        public function route(string $name, array $args = [], $status = 302)
        {
            return $this->to($this->router->generateUri($name, $args), $status);
        }

        /**
         * @param int $status
         * @return \GuzzleHttp\Psr7\MessageTrait
         */
        public function refresh($status = 302)
        {
            return $this->to(getRequest()->getUri(), $status);
        }

        /**
         * @param string $path
         * @param int $status
         * @return \GuzzleHttp\Psr7\MessageTrait
         */
        public function away(string $path, $status = 302)
        {
            return $this->to($path, $status);
        }

        /**
         * @param $path
         * @param int $status
         * @return \GuzzleHttp\Psr7\MessageTrait
         */
        public function to($path, $status = 302)
        {
            return getContainer()
                ->response()
                ->withStatus($status)
                ->withHeader('Location', $path)
            ;
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
                new Twig_SimpleFunction('asset', [$this, 'asset']),
                new Twig_SimpleFunction('path', [$this, 'path']),
                new Twig_SimpleFunction('flash', [$this, 'flash']),
                new Twig_SimpleFunction('logout', [$this, 'logout']),
                new Twig_SimpleFunction('login', [$this, 'login']),
                new Twig_SimpleFunction('user', [$this, 'user']),
                new Twig_SimpleFunction('mix', [$this, 'mix'], ['is_safe' => ['html']]),
                new Twig_SimpleFunction('input_csrf', [$this, 'csrf'], ['is_safe' => ['html']])
            ];
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
         * @throws TypeError
         */
        public function old(string $key, $default = null)
        {
            /** @var array $inputs */
            $inputs = $this->getContainer()->getSession()->get('old_inputs', []);

            return isAke($inputs, $key, $default);
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
         *
         * @return string
         */
        public function asset(string $asset)
        {
            $path = $this->getContainer()->define("asset_path");

            if (!$path) {
                $path = '/assets/';
            }

            return $path . $asset;
        }

        /**
         * @param string $routeName
         * @param array $params
         *
         * @return string
         */
        public function path(string $routeName, array $params = [])
        {
            /**
             * @var $fastRouter FastRouter
             */
            $fastRouter = $this->getContainer()->define("router");

            return $fastRouter->generateUri($routeName, $params);
        }

        /**
         * @return string
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

    class FastObject
    extends Objet
    implements
    FastUserOrmInterface,
    FastRouterInterface,
    FastRouteInterface,
    FastRoleOrmInterface {}
