<?php
use Interop\Http\ServerMiddleware\DelegateInterface;
use Octo\App;
use Octo\Config;
use Octo\Facader;
use Octo\Fast;
use Octo\FastMiddleware;
use Octo\Fastmiddlewarecsrf;
use Octo\FastRendererInterface;
use Octo\FastRequest;
use Octo\FastUserOrmInterface;
use Octo\Flash;
use Octo\Objet;
use Octo\Octal;
use Octo\Reflector;
use Octo\Resolver;
use function Octo\sessionKey;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\Router\FastRouteRouter;

function testWithId($id)
{
    return $id * 5;
}

class MyEvents extends Facader {}

class ShareClass
{
    private $object;

    /**
     * ShareClass constructor.
     * @param $object
     * @throws ReflectionException
     */
    public function __construct($object)
    {
        $this->object = new Reflector($object);
    }

    public function __call($name, $arguments)
    {
        return $this->object->{$name}(...$arguments);
    }
}

class myRequest extends FastRequest {}

class DataEntity extends Octal {}

class TestMiddleware extends FastMiddleware
{
    public function process(ServerRequestInterface $request, DelegateInterface $next)
    {
        $this->actual('geoloc.test', $request->getAttribute('geolocation'));

        return $next->process($request);
    }
}

class PDODummy extends PDO {}

class stdClassDummy
{
    public function __construct(PDODummy $pdo, Objet $object)
    {
        $this->object = $object;
        $this->object->pdo = $pdo;
    }
}

class ModulePhp extends Octo\Module
{
    /**
     * @var FastRendererInterface
     */
    private $renderer;

    public function __construct()
    {
        $this->setViewPath(__DIR__ . '/blade');
        parent::__construct();
    }

    /**
     * @param ContainerInterface $app
     */
    public function config(ContainerInterface $app)
    {
        $app->phpRenderer(__DIR__ . DIRECTORY_SEPARATOR . 'views');

        $this->renderer = $app->getRenderer();
    }

    /**
     * @param $router
     */
    public function routes($router)
    {
        $router
            ->addRoute('GET', '/demo', [$this, 'demo'])
        ;
    }

    /**
     * @param myRequest $request
     *
     * @return mixed
     */
    public function demo(myRequest $request)
    {
        return $this->renderer->render('demo', ['name' => 'test']);
    }
}

class TestRouteMiddleware
{
    /**
     * @param ServerRequestInterface $request
     * @param $next
     * @return mixed
     * @throws ReflectionException
     */
    public function process(Psr\Http\Message\ServerRequestInterface $request, $next)
    {
        if ($slug = $request->getAttribute('slug')) {
            return $next->process($request);
        }
    }
}
class RetestRouteMiddleware
{
    /**
     * @param ServerRequestInterface $request
     * @param $next
     * @return mixed
     * @throws ReflectionException
     */
    public function process(Psr\Http\Message\ServerRequestInterface $request, $next)
    {
        if ($slug = $request->getAttribute('slug')) {
            return $next->process($request);
        }
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

    /**
     * @param stdClassDummy $dummy
     */
    public function __construct(stdClassDummy $dummy)
    {
        $this->dummy = $dummy;
        $this->setViewPath(__DIR__ . '/blade');
        parent::__construct();
    }

    /**
     * @param ContainerInterface $app
     */
    public function config(ContainerInterface $app)
    {
        $app->twigRenderer(__DIR__ . DIRECTORY_SEPARATOR . 'twig');
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

    /**
     * @param Objet $router
     * @throws \Octo\Exception
     */
    public function routes(Objet $router)
    {
        $router
            ->addRoute('GET', 'test', [$this, 'test'])
            ->addRoute(
                'GET',
                'slug/{slug}', [
                    $this,
                    'slug'
                ],
                'getSlug', [
                    TestRouteMiddleware::class,
                    RetestRouteMiddleware::class,
                ]
            )
            ->addRoute('GET', 'data/{id:\d+}', [$this, 'data'])
            ->addRoute('GET', 'hello', [$this, 'hello'])
            ->addRoute('GET', 'blade', [$this, 'blade'])
            ->addRoute('GET', 'admin/foo', [$this, 'admin'])
            ->view('view', 'view', 'view')
            ->redirect('redirect', '/hello', 'redirect')
        ;
    }

    /**
     * @param FastRequest $request
     *
     * @return string
     */
    public function test(FastRequest $request)
    {
        return "OK";
    }

    /**
     * @param FastRequest $request
     *
     * @return string
     */
    public function admin(FastRequest $request): string
    {
        return "OK";
    }

    /**
     * @param string $slug
     * @param FastUserOrmInterface $user
     *
     * @return string
     */
    public function slug(string $slug, FastUserOrmInterface $user): string
     {
        return $slug;
     }

    /**
     * @param int $id
     *
     * @return string
     */
     public function data(int $id): string
     {
        $post = DataEntity::find($id);

        return $post->name;
     }

    /**
     * @return mixed
     */
    public function hello()
    {
        return $this->renderer->render('hello', ['name' => 'test']);
    }

    /**
     * @return null|string
     * @throws ReflectionException
     */
    public function blade()
    {
        return $this->render('blade', ['foo' => 'bar']);
    }
}

class FastTest extends TestCase
{
    /**
     * @var Octo\Fast $app
     */
    protected $app;
    protected $engine;
    protected $session;

    /**
     * @throws ReflectionException
     */
    public function setUp()
    {
        parent::setUp();

        $this->session = new Live(new Now(sessionKey()));;

        $this->app = App::create();

        $this->app
            ->set(Octo\FastContainerInterface::class, function () {
                return $this->gi()->make(Octo\Fastcontainer::class);
            })
            ->set(PDODummy::class, function () {
                return new PDODummy('sqlite::memory:');
            })
            ->set(Octo\Fastmiddlewarecsrf::class, function () {
                return new Octo\Fastmiddlewarecsrf($this->session);
            })
            ->set(Geocoder\Geocoder::class, function () {
                return Octo\Fastmiddlewaregeo::createGeocoder();
            })
            ->set(Octo\FastSessionInterface::class, function () {
                return $this->session;
            })
            ->set(Octo\FastCacheInterface::class, function () {
                return new Octo\Now;
            })
            ->set(Octo\FastUserOrmInterface::class, function () {
                return $this->fo();
            })
            ->set(Octo\FastRouterInterface::class, function () {
                return App::router();
            })
            ->set(Octo\FastRendererInterface::class, function () {
                return App::renderer();
            })
            ->set(Octo\FastAuthInterface::class, function () {
                /**
                 * @var Octo\Objet $auth
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
                    return App::session();
                });

                return $auth;
            })
            ->addMiddleware(Octo\Fastmiddlewaretrailingslash::class)
            ->addMiddleware(Octo\Fastmiddlewarecsrf::class)
            ->addMiddleware(Octo\Fastmiddlewaregeo::class)
            ->addMiddleware(TestMiddleware::class)
            ->addMiddleware(Octo\Fastmiddlewareacl::class)
            ->addMiddleware(Octo\Fastmiddlewaremustbeauthorized::class)
            ->addMiddleware(Octo\Fastmiddlewarerouter::class)
            ->addMiddleware(Octo\Fastmiddlewaredispatch::class)
            ->addMiddleware(Octo\Fastmiddlewarenotfound::class)
        ;

        $shareClass = new ShareClass($this->app);

        $this->setInstance($shareClass);

        $this->engine = Octo\conf('octalia.engine');
        Config::set('octalia.engine', 'ndb');

        MyEvents::test(function ($a, $b) {
            return $a * $b;
        });
    }

    public function tearDown()
    {
        parent::tearDown();

        Config::set('octalia.engine', $this->engine);
    }

    /**
     * @throws Exception
     */
    public function testFacader()
    {
        $this->assertSame(15, MyEvents::test(5, 3));
    }

    /**
     * @throws Exception
     */
    public function testShare()
    {
        $shared = $this->getInstance(ShareClass::class);
        $this->assertInstanceOf(ShareClass::class, $shared);

        $this->assertSame(Fast::class, $shared->getName());
    }

    /**
     * @throws Exception
     */
    public function testRequest()
    {
        $this
            ->assertInstanceOf(
                ServerRequestInterface::class,
                $this->app->getRequest()
            )
        ;
    }

    /**
     * @throws Exception
     */
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

        $this->app->set('test', 128);
        $this->assertEquals(128, $this->app->test);
        $this->assertEquals(128, $this->app['test']);
        $this->assertEquals(128, $this->app->get('test'));

        $this->app['test'] = 123;
    }

    /**
     * @throws Exception
     */
    public function testNotEmptyAfterInstanciated()
    {
        $this->assertEquals(123, $this->app['test']);
        $this->assertEquals(123, $this->app->test);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeError
     */
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

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeError
     */
    public function testNotFound()
    {
        $_SERVER['REQUEST_URI'] = '/dummy';
        $request = $this->app->fromGlobals();
        $this->app->addModule(Module::class);
        $response = $this->app->run($request);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals(404, $this->app->getResponse()->getStatusCode());
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function testCsrf()
    {
        $middleware = new Fastmiddlewarecsrf($this->session);

        $_SERVER['REQUEST_METHOD'] = 'POST';

        $csrf = $_POST[$middleware->getFormKey()] = $middleware->generateToken();

        $this->app->fromGlobals();

        $tokens = $this->session[$middleware->getSessionKey()] ?? [];

        $this->assertCount(1, $tokens);
        $this->assertTrue(in_array($csrf, $tokens));
        $this->assertTrue($this->csrf_match());

        $_SERVER['REQUEST_METHOD'] = 'GET';
    }


    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeError
     */
    public function testAuth()
    {
        $_SERVER['REQUEST_URI'] = '/admin/foo';
        $request = $this->app->fromGlobals();
        $this->app->addModule(Module::class);
        $response = $this->app->run($request);

        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals(301, $this->app->getResponse()->getStatusCode());
        $this->assertEquals('/admin/login', current($response->getHeader('Location')));
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeError
     */
    public function testTrailingSlashRedirect()
    {
        $_SERVER['REQUEST_URI'] = '/slug/foo/';
        $request = $this->app->fromGlobals();
        $this->app->addModule(Module::class);
        $response = $this->app->run($request);

        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals(301, $this->app->getResponse()->getStatusCode());
        $this->assertEquals('/slug/foo', current($response->getHeader('Location')));
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeError
     */
    public function testAddModuleSimpleUrl()
    {
        $_SERVER['REQUEST_URI'] = '/test';
        $request = $this->app->fromGlobals();
        $this->app->addModule(Module::class);
        $response = $this->app->run($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', (string) $response->getBody());
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeError
     */
    public function testAddModuleArgsUrl()
    {
        $_SERVER['REQUEST_URI'] = '/slug/foo';
        $request = $this->app->fromGlobals();
        $this->app->addModule(Module::class);
        $response = $this->app->run($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('foo', (string) $response->getBody());
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeError
     */
    public function testData()
    {
        DataEntity::store(['name' => 'test']);
        $_SERVER['REQUEST_URI'] = '/data/1';
        $request = $this->app->fromGlobals();
        $this->app->addModule(Module::class);
        $response = $this->app->run($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('test', (string) $response->getBody());
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeError
     */
    public function testRedirect()
    {
        $_SERVER['REQUEST_URI'] = '/redirect';
        $request = $this->app->fromGlobals();
        $this->app->addModule(Module::class);
        $response = $this->app->run($request);

        $this->assertEquals('/hello', current($response->getHeader('Location')));
        $this->assertEquals(301, $response->getStatusCode());
        $this->assertTrue($this->app->isRedirection());
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeError
     */
    public function testView()
    {
        $_SERVER['REQUEST_URI'] = '/view';
        $request = $this->app->fromGlobals();
        $this->app->addModule(Module::class);
        $response = $this->app->run($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($this->app->isSuccessful());
        $this->assertEquals('view test', (string) $response->getBody());
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeError
     */
    public function testTwig()
    {
        $_SERVER['REQUEST_URI'] = '/hello';
        $request = $this->app->fromGlobals();
        $this->app->addModule(Module::class);
        $response = $this->app->run($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<h1>Hello test <a href="/slug/foo">link</a></h1>', (string) $response->getBody());
    }

    /**
     * @throws ReflectionException
     */
    public function testBlade()
    {
        $_SERVER['REQUEST_URI'] = '/blade';
        $request = $this->app->fromGlobals();
        $this->app->addModule(Module::class);
        $response = $this->app->run($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('<h1>Hello bar</h1>', (string) $response->getBody());
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeError
     */
    public function testPhpRenderer()
    {
        $_SERVER['REQUEST_URI'] = '/demo';
        $request = $this->app->fromGlobals();
        $this->app->addModule(ModulePhp::class);
        $response = $this->app->run($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($this->app->isOk());
        $this->assertEquals('<h1>Hello test</h1>', (string) $response->getBody());
    }

    /**
     * @throws ReflectionException
     */
    public function testInstanciator()
    {
        $request = $this->app->fromGlobals();
        $this->app->setRequest($request->withAttribute('id', 1));

        $result = \Octo\gi()->callMethod('testWithId');

        $this->assertSame(5, $result);
    }

    /**
     * @throws Exception
     */
    public function testDirectAssignations()
    {
        $app = $this->app->dummy(1);
        $this->assertEquals(1, $app->dummy());
        $this->assertEquals(1, $app->dummy);
        $app->isDummy(1);
        $this->assertEquals(1, $app->isDummy());
        $this->assertEquals(1, $app->is_dummy);
    }

    /**
     * @throws Exception
     */
    public function testCache()
    {
        $cache = $this->getContainer()->get(Octo\FastCacheInterface::class);

        $cache->set('foo', 'bar');

        $this->assertSame('bar', $cache->get('foo'));

        $number = 1;

        $cache->set('number', $number);

        $cache->incr('number', 9);

        $this->assertSame(10, $cache->get('number'));
    }

    /**
     * @throws Exception
     */
    public function testContainer()
    {
        /** @var Octo\FastContainer $container */
        $container = $this->getDI();

        $this->assertTrue($container->has(Octo\FastContainerInterface::class));

        $this->assertInstanceOf(
            Octo\FastContainerInterface::class,
            $container->get(Octo\FastContainerInterface::class)
        );

        $this->assertInstanceOf(
            FastRouteRouter::class,
            $this->getRouter()
        );

        $this->assertEquals($container, $this->getDI());
        $this->assertEquals($container, $container->getDI()->getDI());

        $this->assertInstanceOf(Fast::class, $this->getContainer()->self('fast'));
    }

    /**
     * @throws Exception
     */
    public function testFlash()
    {
        $flash = $this->gi()->make(Flash::class);

        $flash->success('whaou');
        $flash->fail('oups');

        $this->assertTrue($flash->hasSuccess());
        $this->assertTrue($flash->hasFail());

        $this->assertEquals('whaou', $flash->success());
        $this->assertCount(1, $this->session[$flash->getStorageKey()]);

        $this->assertEquals('oups', $flash->fail());
        $this->assertEmpty($this->session[$flash->getStorageKey()]);

        $this->assertTrue($flash->hasSuccess());
        $this->assertTrue($flash->hasFail());

        $this->assertEquals('whaou', $flash->success());
        $this->assertEquals('oups', $flash->fail());
    }

    public function lazytest()
    {
        $this->incr('test', 8);
    }

    /**
     * @throws Exception
     */
    public function testResolver()
    {
        $lazy = $this->lazy([$this, 'lazytest']);

        $this->assertEquals(1, $this->incr('test'));
        $lazy();
        $this->assertEquals(10, $this->incr('test'));

        $this->assertInstanceOf(stdClass::class, Resolver::factory(stdClass::class));
        $this->assertInstanceOf(stdClass::class, Resolver::lazy(stdClass::class)());
        $this->assertSame(Resolver::factory(stdClass::class), Resolver::lazy(stdClass::class)());
    }

    /**
     * @throws Exception
     */
    public function testBlader()
    {
        $str = '<h1>Test {{$name}}</h1>';

        $compiled = $this->blader($str, ['name' => 'Foo']);

        $this->assertSame('<h1>Test Foo</h1>', $compiled);
    }
}
