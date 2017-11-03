<?php
    namespace Octo;

    use ArrayAccess;
    use ArrayObject;
    use Exception as NativeException;
    use GuzzleHttp\Psr7\Response as Psr7Response;
    use GuzzleHttp\Psr7\ServerRequest as Psr7Request;
    use Interop\Http\ServerMiddleware\DelegateInterface;
    use Interop\Http\ServerMiddleware\MiddlewareInterface;
    use Psr\Container\ContainerInterface;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Twig_Environment;
    use Twig_Extension;
    use Twig_Filter;
    use Twig_SimpleFunction;
    use Zend\Expressive\Router\FastRouteRouter as FastRouter;
    use Zend\Expressive\Router\Route as FastRoute;
    use function Http\Response\send as sendResponse;

    class Fast extends ArrayObject implements
        FastContainerInterface,
        ArrayAccess,
        DelegateInterface,
        ContainerInterface
    {
        /**
         * @var Fastcontainer
         */
        protected $app;

        protected $request, $router, $middlewares = [], $extensionsLoaded = [];

        public function __construct($config = null)
        {
            Timer::start();

            $config = arrayable($config) ? $config->toArray() : $config;

            $this->app = $this->maker(Fastcontainer::class);

            if ($config && is_array($config)) {
                foreach ($config as $key => $value) {
                    Config::set($key, $value);
                }
            }

            $this->request = $this->fromGlobals();

            actual('fast', $this);
        }

        public function add($key, $value)
        {
            $concern = 'add.fast.' . $key;

            $old = $this->app->dataget($concern, []);

            $new = array_merge($old, [$value]);

            $this->app->dataset($concern, $new);
        }

        /**
         * @return ServerRequestInterface
         */
        public function fromGlobals()
        {
            return Psr7Request::fromGlobals();
        }

        /**
         * @param ServerRequestInterface $request
         * @return $this
         */
        public function setRequest(ServerRequestInterface $request)
        {
            $this->request = $request;

            return $this;
        }

        public function setUser($user)
        {
            $this->define('user', $user);

            return $this;
        }

        public function getUser()
        {
            return $this->define('user');
        }

        /**
         * @param $session
         * @throws \TypeError
         */
        private function testSession($session)
        {
            if (!is_array($session) && !$session instanceof ArrayAccess) {
                throw new \TypeError('session is not valid');
            }
        }

        public function setSession($session)
        {
            $this->testSession($session);
            $this->define('session', $session);

            return $this;
        }

        public function getSession()
        {
            $session = $this->define('session');

            $this->testSession($session);

            return $session ?: [];
        }

        /**
         * @param FastRendererInterface $renderer
         * @return $this
         */
        public function setRenderer(FastRendererInterface $renderer)
        {
            $this->define('renderer', $renderer);

            return $this;
        }

        public function getRenderer(): FastRendererInterface
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
                        $twig->addExtension(maker($extension));
                    } catch (\Exception $e) {
                        $this->extensionsLoaded[] = $extension;
                    }
                }
            }
        }

        public function setAuth($auth)
        {
            $this->define('auth', $auth);

            return $this;
        }

        public function getAuth()
        {
            return $this->define('auth');
        }

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
         * @param string $class
         * @param bool $singleton
         *
         * @return mixed|object
         */
        public function resolve($class, $singleton = true)
        {
            return $singleton ? maker($class) : foundry($class);
        }

        /**
         * @return Object
         */
        public function getContainer()
        {
            return $this->app;
        }

        /**
         * @return Psr7Response
         */
        public function response()
        {
            return call_user_func_array(
                'Octo\foundry',
                array_merge([Psr7Response::class], func_get_args())
            );
        }

        public function redirectResponse($uri)
        {
            return $this->response()
                ->withStatus(301)
                ->withHeader('Location', $uri);
        }

        public function redirectRouteResponse($route)
        {
            $uri = $this->router()->urlFor($route);

            return $this->redirectResponse($uri);
        }

        /**
         * @param null $request
         * @return ResponseInterface
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

        public function process(ServerRequestInterface $request)
        {
            $middleware = $this->getMiddleware();

            if (is_null($middleware)) {
                exception('fast', 'no middleware intercepts request');
            } elseif ($middleware instanceof MiddlewareInterface) {
                $methods = get_class_methods($middleware);

                if (in_array('process', $methods)) {
                    return callMethod($middleware, 'process', $request, $this);
                }

                if (in_array('handle', $methods)) {
                    return callMethod($middleware, 'handle', $request, $this);
                }
            } elseif (is_callable($middleware)) {
                return call_user_func_array($middleware, [$request, [$this, 'process']]);
            }
        }

        /**
         * @param $middlewareClass
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

        private function getMiddleware()
        {
            $middlewares    = $this->middlewares;
            $middleware     = array_shift($middlewares);

            $this->middlewares = $middlewares;

            if (is_string($middleware)) {
                return maker($middleware);
            } elseif (is_callable($middleware)) {
                $middleware = call_user_func_array($middleware, [$this]);

                if (is_string($middleware)) {
                    return maker($middleware);
                } else {
                    return $middleware;
                }
            } else {
                return $middleware;
            }
        }

        /**
         * @param $moduleClass
         * @return Fast
         */
        public function addModule($moduleClass)
        {
            $module = maker($moduleClass);

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

            return $this;
        }

        public function router()
        {
            if (!$this->define('router')) {
                $this->define('router', new FastRouter);
            }

            if (!isset($this->router)) {
                $router = fo();

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
                            $fastRouter = $this->define('router');

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
                        $fastRouter = $this->define('router');

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
                        $fastRouter = $this->define('router');
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
                    $fastRouter = $this->define('router');

                    return $fastRouter->generateUri($name, $params);
                });

                $router->macro('match', function ($request = null) {
                    $request    = is_null($request) ? $this->getRequest() : $request;

                    /**
                     * @var $fastRouter FastRouter
                     */
                    $fastRouter = $this->define('router');
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

        public function define(string $key, $value = 'octodummy')
        {
            $keyDefine = 'fast.' . $key;

            if ('octodummy' == $value) {
                return actual($keyDefine);
            }

            return actual($keyDefine, $value);
        }

        /**
         * @param string $routeName
         * @param array $params
         *
         * @return string
         */
        public function path(string $routeName, array $params)
        {
            /**
             * @var $fastRouter FastRouter
             */
            $fastRouter = actual('fast.router');

            return $fastRouter->generateUri($routeName, $params);
        }
    }

    /* Interfaces */
    interface FastOrmInterface {}
    interface FastExceptionInterface {}
    interface FastSessionInterface {}
    interface FastCacheInterface {}
    interface FastFlashInterface {}
    interface FastLogInterface {}
    interface FastDbInterface {}
    interface FastMailerInterface {}
    interface FastEventInterface {}
    interface FastViewInterface {}
    interface FastRouterInterface {}
    interface FastRouteInterface {}
    interface FastRendererInterface {}
    interface FastAuthInterface {}
    interface FastStorageInterface {}
    interface FastContainerInterface
    {
        public function get($key, $singleton = false);
        public function has($key);
    }

    interface FastUserOrmInterface {}
    interface FastRoleOrmInterface {}

    class FastRedis extends Cacheredis  implements FastStorageInterface {}
    class FastCache extends Cache       implements FastStorageInterface {}
    class FastNow   extends Now         implements FastStorageInterface {}

    class FastTwigExtensions extends Twig_Extension
    {
        use FastTrait;
    }

    trait FastTrait
    {
        /**
         * @param string $method
         * @param array $args
         *
         * @return mixed
         */
        public function __call($method, $args)
        {
            $method = '\\Octo\\' . $method;

            if (function_exists($method)) {
                return call_user_func_array($method, $args);
            }
        }

    }

    class FastPhpRenderer implements FastRendererInterface
    {
        use FastTrait;

        /**
         * @param string $name
         * @param array $context
         *
         * @return string
         */
        public function render($name, array $context = [])
        {
            if (!File::exists($name)) {
                $viewPath = actual('fast')->define('view.path');

                if (is_null($viewPath)) {
                    exception('FastPhpRenderer', 'Please provide a view path.');
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
    }

    class FastTwigRenderer extends Twig_Environment implements FastRendererInterface
    {
        use FastTrait;

        /**
         * @param string $name
         * @param array $context
         *
         * @return string
         */
        public function render($name, array $context = [])
        {
            return parent::render($name . '.twig', $context);
        }
    }

    class FastMiddleware implements MiddlewareInterface
    {
        use FastTrait;

        public function process(ServerRequestInterface $request, DelegateInterface $next)
        {
            return $next->process($request);
        }
    }

    class FastTwigExtension extends FastTwigExtensions
    {
        public function getFunctions()
        {
            return [
                new Twig_SimpleFunction('dump', [$this, 'dump'], ['is_safe' => ['html']]),
                new Twig_SimpleFunction('path', [$this, 'path']),
                new Twig_SimpleFunction('logout', [$this, 'logout']),
                new Twig_SimpleFunction('login', [$this, 'login']),
                new Twig_SimpleFunction('csrf', [$this, 'csrf'])
            ];
        }

        public function getFilters()
        {
            return [
                new Twig_Filter('camelize', ['Octo\Inflector', 'camelize']),
                new Twig_Filter('uncamelize', ['Octo\Inflector', 'uncamelize']),
                new Twig_Filter('strlen', ['Octo\Inflector', 'length']),
            ];
        }

        public function path($routeName, array $params = [])
        {
            /**
             * @var $fastRouter FastRouter
             */
            $fastRouter = actual('fast.router');

            return $fastRouter->generateUri($routeName, $params);
        }

        public function logout()
        {
            /**
             * @var $fastRouter FastRouter
             */
            $fastRouter = actual('fast.router');

            return $fastRouter->generateUri('logout');
        }

        public function login()
        {
            /**
             * @var $fastRouter FastRouter
             */
            $fastRouter = actual('fast.router');

            return $fastRouter->generateUri('login');
        }

        public function csrf($tokenName = '_csrf', $sessionKey = 'csrf.tokens')
        {
            return csrf($tokenName, $sessionKey);
        }

        public function dump($context)
        {
            $dump = fopen('php://memory', 'r+b');

            lvd($context);

            return stream_get_contents($dump, -1, 0);
        }
    }

    class FastException extends NativeException implements FastExceptionInterface {}

    class FastObject
    extends Object
    implements
    FastUserOrmInterface,
    FastRouterInterface,
    FastRouteInterface,
    FastRoleOrmInterface {}
