<?php
    use Interop\Http\ServerMiddleware\DelegateInterface;
    use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Octo\FastRendererInterface;
use Octo\FastUserOrmInterface;
    use Psr\Container\ContainerInterface;
    use Psr\Http\Message\ServerRequestInterface;

    class TestMiddleware implements MiddlewareInterface
    {
        public function process(ServerRequestInterface $request, DelegateInterface $next)
        {
            Octo\actual('geoloc.test', $request->getAttribute('geolocation'));

            return $next->process($request);
        }
    }

    class PDODummy extends PDO {}

    class stdClassDummy
    {
        public function __construct(PDODummy $pdo, Octo\Object $object)
        {
            $this->object = $object;
            $this->object->pdo = $pdo;
        }
    }

    class Module extends Octo\Module
    {
        /**
         * @var stdClassDummy
         */
        private $dummy;

        /**
         * @var FastRendererInterface
         */
        private $renderer;


        /**
         * @var ContainerInterface
         */
        private $app;

        public function __construct(stdClassDummy $dummy)
        {
            $this->dummy = $dummy;
        }

        public function config(ContainerInterface $app)
        {
            $this->twig(__DIR__ . DIRECTORY_SEPARATOR . 'twig');
//            $this->blade(__DIR__ . DIRECTORY_SEPARATOR . 'blade');
        }

        /**
         * @param ContainerInterface $app
         * @param FastRendererInterface $renderer
         */
        public function di(ContainerInterface $app, FastRendererInterface $renderer)
        {
            $this->app = $app;
            $this->renderer = $renderer;
        }

        public function routes($router)
        {
            $router
                ->addRoute('GET', '/test', static::class, 'test')
                ->addRoute('GET', '/slug/{slug}', static::class, 'slug')
                ->addRoute('GET', '/hello', static::class, 'hello')
                ->addRoute('GET', '/admin/foo', static::class, 'admin')
            ;
        }

        public function test(ServerRequestInterface $request)
        {
            return "OK";
        }

        public function admin(ServerRequestInterface $request)
        {
            return "OK";
        }

         public function slug(
            ServerRequestInterface $request,
            ContainerInterface $app,
            FastUserOrmInterface $user) {
            return $request->getAttribute('slug');
        }

        public function hello()
        {
            return $this->renderer->render('hello', ['name' => 'test']);
        }
    }

    class FastTest extends TestCase
    {
        /**
         * @var Octo\Fast $app
         */
        protected $app;

        public function setUp()
        {
            parent::setUp();

            $this->app = $this->foundry('fast');

            $this->session = new Octo\Sessionarray;

            $this->app
                ->register(PDODummy::class, function () {
                    return new PDODummy('sqlite::memory:');
                })
                ->register(Octo\Fastmiddlewarecsrf::class, function () {
                    return new Octo\Fastmiddlewarecsrf($this->session);
                })
                ->register(Geocoder\Geocoder::class, function () {
                    return Octo\Fastmiddlewaregeo::createGeocoder();
                })
                ->register(Octo\FastSessionInterface::class, function () {
                    return new Octo\Sessionarray;
                })
                ->register(Octo\FastUserOrmInterface::class, function () {
                    return $this->fo();
                })
                ->register(Octo\FastRouterInterface::class, function () {
                    return $this->actual("fast")->router();
                })
                ->register(Octo\FastRendererInterface::class, function () {
                    return $this->actual("fast")->getRenderer();
                })
                ->addMiddleware(Octo\Fastmiddlewaretrailingslash::class)
                ->addMiddleware(Octo\Fastmiddlewarecsrf::class)
                ->addMiddleware(Octo\Fastmiddlewaregeo::class)
                ->addMiddleware(TestMiddleware::class)
                ->addMiddleware(Octo\Fastmiddlewareacl::class)
                ->addMiddleware(function (Octo\Fast $app) {
                    $request = $app->getRequest();

                    $path = $request->getUri()->getPath();

                    if (!fnmatch('/admin*', $path)) {
                        return Octo\FastMiddleware::class;
                    }

                    /**
                     * @var Octo\Object $auth
                     */
                    $auth = $this->fo();

                    $auth->macro('getLoginPath', function () {
                        return '/admin/login';
                    });

                    $auth->macro('getLogoutPath', function () {
                        return '/admin/logout';
                    });

                    $auth->macro('getUser', function () {
                        return null;
                    });

                    $auth->macro('getSession', function () {
                        return $this->actual('fast')->getSession();
                    });

                    $app->setAuth($auth);

                    return Octo\Fastmiddlewareauth::class;
                })
                ->addMiddleware(Octo\Fastmiddlewarerouter::class)
                ->addMiddleware(Octo\Fastmiddlewaredispatch::class)
                ->addMiddleware(Octo\Fastmiddlewarenotfound::class)
            ;
        }

        public function testRequest()
        {
            $this
                ->assertInstanceOf(
                    ServerRequestInterface::class,
                    $this->app->getRequest()
                )
            ;
        }

        public function testBag()
        {
            $this->app['test'] = 123;
            $this->assertEquals(123, $this->app['test']);
            $this->assertEquals(123, $this->app->test);

            $this->app->test = 124;
            $this->assertEquals(124, $this->app->test);
            $this->assertEquals(124, $this->app['test']);

            $this->app->test++;
            $this->assertEquals(125, $this->app->test);
            $this->assertEquals(125, $this->app['test']);

            $this->app->delete('test');
            $this->assertNull($this->app['test']);
            $this->assertNull($this->app->test);

            $this->app['test'] = 123;
        }

        public function testNotEmptyAfterInstanciated()
        {
            $this->assertEquals(123, $this->app['test']);
            $this->assertEquals(123, $this->app->test);
        }

        public function Geo()
        {
            $_SERVER['REMOTE_ADDR'] = '77.207.10.26';
            $request = $this->app->fromGlobals();
            $this->app->addModule(Module::class);
            $this->app->run($request);

            /**
             * @var Geocoder\Model\AddressCollection $geoloc
             */
            $geoloc = $this->actual('geoloc.test');
            $this->assertInstanceOf(Geocoder\Model\AddressCollection::class, $geoloc);
            $this->assertEquals(1, $geoloc->count());
            $this->assertEquals('France', $geoloc->first()->getCountry()->getName());
        }

        public function testNotFound()
        {
            $_SERVER['REQUEST_URI'] = '/dummy';
            $request = $this->app->fromGlobals();
            $this->app->addModule(Module::class);
            $response = $this->app->run($request);

            $this->assertEquals(404, $response->getStatusCode());
        }

        public function testAuth()
        {
            $_SERVER['REQUEST_URI'] = '/admin/foo';
            $request = $this->app->fromGlobals();
            $this->app->addModule(Module::class);
            $response = $this->app->run($request);

            $this->assertEquals(301, $response->getStatusCode());
            $this->assertEquals('/admin/login', current($response->getHeader('Location')));
        }

        public function testTrailingSlashRedirect()
        {
            $_SERVER['REQUEST_URI'] = '/slug/foo/';
            $request = $this->app->fromGlobals();
            $this->app->addModule(Module::class);
            $response = $this->app->run($request);

            $this->assertEquals(301, $response->getStatusCode());
            $this->assertEquals('/slug/foo', current($response->getHeader('Location')));
        }

        public function testAddModuleSimpleUrl()
        {
            $_SERVER['REQUEST_URI'] = '/test';
            $request = $this->app->fromGlobals();
            $this->app->addModule(Module::class);
            $response = $this->app->run($request);

            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals('OK', (string) $response->getBody());
        }

        public function testAddModuleArgsUrl()
        {
            $_SERVER['REQUEST_URI'] = '/slug/foo';
            $request = $this->app->fromGlobals();
            $this->app->addModule(Module::class);
            $response = $this->app->run($request);

            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals('foo', (string) $response->getBody());
        }

        public function testTwig()
        {
            $_SERVER['REQUEST_URI'] = '/hello';
            $request = $this->app->fromGlobals();
            $this->app->addModule(Module::class);
            $response = $this->app->run($request);

            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals('<h1>Hello test <a href="/slug/foo">link</a></h1>', (string) $response->getBody());
        }

        public function testDirectAssignations()
        {
            $app = $this->app->dummy(1);
            $this->assertEquals(1, $app->dummy());
            $this->assertEquals(1, $app->dummy);
            $app->isDummy(1);
            $this->assertEquals(1, $app->isDummy());
            $this->assertEquals(1, $app->is_dummy);
        }
    }
