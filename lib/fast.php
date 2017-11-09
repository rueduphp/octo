<?php
    namespace Octo;

    use ArrayAccess;
    use ArrayObject;
    use Exception as NativeException;
    use GuzzleHttp\Psr7\MessageTrait;
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

        protected $response, $request, $router, $middlewares = [], $extensionsLoaded = [];

        public function __construct($config = null)
        {
            Timer::start();

            $config = arrayable($config) ? $config->toArray() : $config;

            $this->app = instanciator()->singleton(Fastcontainer::class);

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
            return $singleton ? instanciator()->singleton($class) : instanciator()->factory($class);
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

        /**
         * @param $uri
         * @return MessageTrait|static
         */
        public function redirectResponse($uri)
        {
            $this->response = $this->response()
                ->withStatus(301)
                ->withHeader('Location', $uri)
            ;

            return $this->response;
        }

        public function redirectRouteResponse($route)
        {
            $uri = $this->router()->urlFor($route);

            return $this->redirectResponse($uri);
        }

        /**
         * @param null $request
         *
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
                    return instanciator()->singletoner($middleware);
                } else {
                    return $middleware;
                }
            } else {
                return $middleware;
            }
        }

        /**
         * @param $moduleClass
         *
         * @return Fast
         */
        public function addModule($moduleClass)
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
            $fastRouter = $this->define("router");

            return $fastRouter->generateUri($routeName, $params);
        }

        /**
         * @param array $context
         * @return array
         */
        function beforeRender($context = [])
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
        public function isInvalid()
        {
            $statusCode = isset($this->response) ? $this->response->getStatusCode() : 0;

            return $statusCode < 100 || $statusCode >= 600;
        }

        /**
         * @return bool
         */
        public function isInformational()
        {
            $statusCode = isset($this->response) ? $this->response->getStatusCode() : 0;

            return $statusCode >= 100 && $statusCode < 200;
        }

        /**
         * @return bool
         */
        public function isSuccessful()
        {
            $statusCode = isset($this->response) ? $this->response->getStatusCode() : 0;

            return $statusCode >= 200 && $statusCode < 300;
        }

        /**
         * @return bool
         */
        public function isRedirection()
        {
            $statusCode = isset($this->response) ? $this->response->getStatusCode() : 0;

            return $statusCode >= 300 && $statusCode < 400;
        }

        /**
         * @return bool
         */
        public function isClientError()
        {
            $statusCode = isset($this->response) ? $this->response->getStatusCode() : 0;

            return $statusCode >= 400 && $statusCode < 500;
        }

        /**
         * @return bool
         */
        public function isServerError()
        {
            $statusCode = isset($this->response) ? $this->response->getStatusCode() : 0;

            return $statusCode >= 500 && $statusCode < 600;
        }

        /**
         * @return bool
         */
        public function isOk()
        {
            $statusCode = isset($this->response) ? $this->response->getStatusCode() : 0;

            return 200 === $statusCode;
        }

        /**
         * @return bool
         */
        public function isForbidden()
        {
            $statusCode = isset($this->response) ? $this->response->getStatusCode() : 0;

            return 403 === $statusCode;
        }

        /**
         * @return bool
         */
        public function isNotFound()
        {
            $statusCode = isset($this->response) ? $this->response->getStatusCode() : 0;

            return 404 === $statusCode;
        }

        /**
         * @return bool
         */
        public function isEmpty()
        {
            $statusCode = isset($this->response) ? $this->response->getStatusCode() : 0;

            return in_array($statusCode, [204, 304]);
        }

        /**
         * @return Psr7Response
         */
        public function getResponse()
        {
            return isset($this->response) ? $this->response : $this->response();
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

    class AuthmiddlewareException extends NativeException {}
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

        /**
         * @return Fast
         */
        public function getContainer()
        {
            return getContainer();
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

            $context = $this->getContainer()->beforeRender($context);

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
            $context = $this->getContainer()->beforeRender($context);

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
                new Twig_SimpleFunction('flash', [$this, 'flash']),
                new Twig_SimpleFunction('logout', [$this, 'logout']),
                new Twig_SimpleFunction('login', [$this, 'login']),
                new Twig_SimpleFunction('input_csrf', [$this, 'csrf'], ['is_safe' => ['html']])
            ];
        }

        public function flash($key = null, $default = null)
        {
            /** @var Flash $flash */
            $flash = $this->getContainer()->resolve(Flash::class);

            if (null !== $key) {
                return $flash->get($key, $default);
            }

            return $flash->all();
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
            $fastRouter = $this->getContainer()->define("router");

            return $fastRouter->generateUri($routeName, $params);
        }

        public function logout()
        {
            /**
             * @var $fastRouter FastRouter
             */
            $fastRouter = $this->getContainer()->define("router");

            return $fastRouter->generateUri('logout');
        }

        public function login()
        {
            /**
             * @var $fastRouter FastRouter
             */
            $fastRouter = $this->getContainer()->define("router");

            return $fastRouter->generateUri('login');
        }

        public function csrf()
        {
            return csrf();
        }

        public function dump()
        {
            $dump = fopen('php://memory', 'r+b');

            $this->dbg(...func_get_args());

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
