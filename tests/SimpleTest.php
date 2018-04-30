<?php

use Octo\Alert;
use Octo\Arrays;
use Octo\Breeze;
use Octo\Component;
use Octo\Config;
use Octo\Dynamicentity;
use Octo\Dynamicmodel;
use Octo\Dynamicrecord;
use Octo\EavAttribute;
use Octo\EavEntity;
use Octo\EavRow;
use Octo\EavValue;
use Octo\Emit;
use Octo\Facade;
use Octo\Finder;
use function Octo\getPdo;
use Octo\In;
use Octo\Inflector;
use Octo\Instanciator;
use Octo\InternalEvents;
use Octo\Iterator;
use Octo\Live;
use Octo\Memorylog;
use Octo\Monkeypatch;
use Octo\Mvc;
use Octo\Notifiable;
use Octo\Now;
use Octo\On;
use Octo\Proxify;
use Octo\Registry;
use Octo\Remember;
use Octo\Sessionarray;
use Octo\Throttle;
use Octo\Trust;
use Octo\You;
use Octo\Your;
use function Octo\sessionKey;

class Notifier
{
    public function handle($post)
    {
        return 'database';
    }

    public function toDatabase($post)
    {
        return $post->toArray();
    }
}

class Stringy extends Facade
{
    public static function getNativeClass()
    {
        return Inflector::class;
    }
}

class PostNotify
{
    use Notifiable;

    public function toArray()
    {
        return ['name' => 'foo', 'type' => 'bar'];
    }

    public function getKey()
    {
        return 1;
    }
}

class MyGuard extends Trust
{
    public function __construct()
    {
        $this->providers['login'] = function () {
            $session = $this->session();

            $session[$this->getUserKey()] = ['id' => 1, 'name' => 'foo', 'email' => 'foo@bar.com'];

            return true;
        };

        $this->providers['logout'] = function () {
            $session = $this->session();

            unset($session[$this->getUserKey()]);

            return true;
        };

        parent::__construct();
    }
}

class Bag extends Octo\Container
{
    public function getDummy()
    {
        return 'dummy';
    }
}

class MyEvent extends Octo\Fire {}

class UserConnected extends \Octo\Connected
{
    protected $table = 'user';

    public function getPdo(): PDO
    {
        return getPdo();
    }
}

class Middleware
{
    public function handle(\Psr\Http\Message\RequestInterface $request, $response, callable $next)
    {
        $uri = $request->getUri()->getPath();
        context('app')->uri = $uri;

        return $next(
            $request,
            $response
        );
    }
}

class Middleware2
{
    public function handle($request, $response, callable $next)
    {
        $_SERVER['REQUEST_URI'] = '/test';

        context('app')->uri2 = $request->getUri()->getPath();

        return $next(
            $request,
            $response
        );
    }
}

class Subscriber
{
    public function getEvents()
    {
        return [
            'test_1' => 'first',
            'test_2' => 'second'
        ];
    }

    public function first()
    {
        context('app')->event_test_value += 1;
    }

    public function second()
    {
        context('app')->event_test_value += 2;
    }
}

class TestIterator
{
    /**
     * @param array $row
     * @return array
     */
    public function __invoke(array $row): array
    {
        $row['price'] = $row['price'] * 2;

        return $row;
    }
}

class TestFactor
{
    /**
     * @var string
     */
    private $test;

    /**
     * @param string $test
     * @return testFactor
     */
    public function setTest(string $test): testFactor
    {
        $this->test = $test;

        return $this;
    }

    /**
     * @return string
     */
    public function getTest(): string
    {
        return $this->test;
    }
}

class ProductEntity extends Dynamicentity
{
    protected $entity = 'test.product';
}

class UsersEntity extends Dynamicentity
{
    protected $entity = 'test.user';

    /**
     * @param Dynamicmodel $query
     * @return Dynamicmodel
     */
    public function scopeSuccess(Dynamicmodel $query)
    {
        return $query->where('age', '>', 20);
    }

    /**
     * @param Dynamicmodel $query
     * @return Dynamicmodel
     */
    public function scopeFail(Dynamicmodel $query)
    {
        return $query->where('age', '<', 20);
    }
}

class UserObserver
{
    public function created(Dynamicrecord $model)
    {
        Octo\incr(UserObserver::class, $model->age);
        $model->age = 40;
    }
}

class ItemIterator extends Dynamicrecord
{
    public function getQuantity()
    {
        return $this->fire(__FUNCTION__)['quantity'] * 2;
    }

    /**
     * @param int $quantity
     * @return ItemIterator
     */
    public function setQuantity(int $quantity): self
    {
        $this['quantity'] = $quantity * 2;

        return $this;
    }
}

class Items extends Dynamicentity
{
    protected $entity = 'test.item';
    protected $iterator = ItemIterator::class;
}

class SimpleTest extends TestCase
{
    /**
     * @throws ReflectionException
     */
    public function testConnected()
    {
        $this->assertSame(10, UserConnected::count());

        $this->assertSame(app('bag'), app('bag'));

        Instant::put('foo', 'bar');

        $this->assertSame(Instant::get('foo'), 'bar');
    }

    /**
     * @throws ReflectionException
     */
    public function testDiAndFacades()
    {
        $this->gi()->set('fooz', 'bar');
        $this->assertSame('bar', $this->gi()->get('fooz'));

        $this->gi()->define(['testtrue' => function () {
            return true;
        }]);
        $this->assertTrue($this->gi()->get('testtrue'));

        /** @var In $app */
        $app = $this->in();
        $this->in('x', 'y');
        $this->in('testfalse', function () {
            return false;
        });

        $this->assertSame($this->in(), $app);
        $this->assertSame($this->in(), $this->in());

        $this->assertSame('y', $app['x']);
        $this->assertFalse($app['testfalse']);
        $this->assertSame('y', $app->x);
        $this->assertFalse($app->testfalse);

        $this->assertTrue($app->has('testfalse'));
        unset($app['testfalse']);
        $this->assertNull($app['testfalse']);
        $this->assertFalse($app->has('testfalse'));

        $this->assertSame($app['app'], $app->app);
        $this->assertSame($app[Inflector::class], $app[Inflector::class]);

        $this->assertFalse(Request::is('home'));
        $this->assertFalse(Session::has('home'));
        $this->assertFalse(Cache::has('home'));

        Session::set('home', 'foo');
        $this->assertTrue(Session::has('home'));
        $this->assertSame('foo', Session::get('home'));

        Cache::set('home', 'foo');
        $this->assertTrue(Cache::has('home'));
        $this->assertSame('foo', Cache::get('home'));

        $app['event']->on('foo', function () {
            return true;
        })->halt();
        $this->assertTrue($app['event']->fire('foo'));

        $app['larevent']->listen('foo', function () {
            return true;
        });
        $this->assertSame([true], $app['larevent']->fire('foo'));

        $app::singleton('singleton', function () {
           return $this->incr('singleton');
        });

        $this->assertSame(1, $app['singleton']);
        $this->assertSame(1, $app['singleton']);
        $this->assertSame(1, $app['singleton']);

        Flash::error('error foo');
        Flash::success('success foo');
        Flash::dummy('dummy foo');
        $this->assertSame(3, Flash::getSession()[Flash::getSessionKey()]->count());

        $this->assertSame(3, Flash::count());
        $this->assertSame(1, Flash::count('successes'));
        $this->assertSame(1, Flash::count('errors'));
        $this->assertSame(1, Flash::count('dummy'));
        $this->assertSame(0, Flash::getSession()[Flash::getSessionKey()]->count());

        $this->assertSame('error foo', current(Flash::errors())['message']);
        $this->assertSame('success foo', current(Flash::successes())['message']);
        $this->assertSame('dummy foo', current(Flash::dummys())['message']);
    }

    public function testFlashIsclearedButNotInInstance()
    {
        $this->assertSame(1, Flash::count('errors'));
        $this->assertSame(1, Flash::count('successes'));
        $this->assertSame(1, Flash::count('dummy'));

        $this->assertSame('error foo', current(Flash::errors())['message']);
        $this->assertSame('success foo', current(Flash::successes())['message']);
        $this->assertSame('dummy foo', current(Flash::dummys())['message']);

        $this->assertSame(0, Flash::getSession()[Flash::getSessionKey()]->count());
    }

    /**
     * @throws ReflectionException
     * @throws \Octo\Exception
     */
    public function testEav()
    {
        Dynamicmodel::migrate();
        ProductEntity::init();
        UsersEntity::observe(UserObserver::class);

        $db = new Dynamicmodel('test.product');

        $db->create(['name' => 'foo', 'price' => 25]);

        $row = $db->find(1);

        $this->assertSame(1, EavEntity::count());
        $this->assertSame(1, EavRow::count());
        $this->assertSame(4, EavAttribute::count());
        $this->assertSame(4, EavValue::count());
        $this->assertSame(1, $row['id']);
        $this->assertSame(25, $row['price']);
        $this->assertSame('foo', $row['name']);

        $db->update(1, ['price' => 100, 'name' => 'bar']);

        $row = $db->find(1);

        $this->assertSame(1, EavEntity::count());
        $this->assertSame(1, EavRow::count());
        $this->assertSame(4, EavAttribute::count());
        $this->assertSame(4, EavValue::count());
        $this->assertSame(1, $row['id']);
        $this->assertSame(100, $row['price']);
        $this->assertSame('bar', $row['name']);

        /** @var Iterator $query */
        $query = $db->where('price', 100)->get();

        $this->assertSame(1, $query->count());

        $this->assertTrue($db->delete(1));

        $this->assertSame(1, EavEntity::count());
        $this->assertSame(0, EavRow::count());
        $this->assertSame(0, EavValue::count());
        $this->assertSame(0, EavAttribute::count());

        $this->assertFalse($db->delete(1));

        $db->create(['name' => 'foo', 'price' => 25]);
        $db->create(['name' => 'bar', 'price' => 50]);
        $db->create(['name' => 'baz', 'price' => 200]);

        $this->assertSame(3, $db->count());
        $this->assertame(2, $db->where('price', '<', 100)->count());
        $this->assertame(2, $db->where('price', '<=', 100)->count());
        $this->assertame(1, $db->where('price', '>', 100)->count());
        $this->assertame(1, $db->where('price', '>=', 100)->count());

        $this->assertSame('bar', $db->sortBy('name')->first()['name']);
        $this->assertSame('foo', $db->sortBy('name')->last()['name']);
        $this->assertSame(25, $db->sortBy('price')->first()['price']);
        $this->assertSame(200, $db->sortBy('price')->last()['price']);

        $this->assertSame('foo', $db->sortByDesc('name')->first()['name']);
        $this->assertSame('bar', $db->sortByDesc('name')->last()['name']);
        $this->assertSame(200, $db->sortByDesc('price')->first()['price']);
        $this->assertSame(25, $db->sortByDesc('price')->last()['price']);

        $models = $db->model();

        $this->assertSame(25, $models->first()->getPrice());
        $this->assertSame(25, $models->first()->price);
        $this->assertSame(25, $models->first()->price());

        $models->first()->price(123);
        $this->assertSame(123, $models->first()->price());

        $models->first()->setPrice(500);
        $this->assertSame(500, $models->first()->getPrice());

        $this->assertSame(3, ProductEntity::count());
//
        $user    = UsersEntity::create(['name' => 'bar', 'age' => 35]);
        $product = ProductEntity::create(['name' => 'user', 'price' => 250, 'user_id' => $user->id]);

        $this->assertSame($user->getName(), $product->user()->getName());
        $this->assertEquals($user->getCreatedAt(), $product->user()->getCreatedAt());
        $this->assertSame($user->getCreatedAt()->timestamp, $product->user()->getCreatedAt()->timestamp);

        $user->sync($product);

        $this->assertSame(1, $user->pivots($product)->count());
        $this->assertSame($product->id, $user->pivots($product)->first()->product()->id);
        $this->assertSame($user->id, $user->pivots($product)->first()->user()->id);
        $this->assertSame(1, $user->allPivots($product)->count());

        $user->product($product);

        $this->assertTrue($user->isDirty());

        $this->assertSame(40, $user->getAge());
        $this->assertSame(35, Octo\get(UserObserver::class));
        $this->assertSame(1, UsersEntity::success()->count());
        $this->assertSame(0, UsersEntity::fail()->count());
        $copy = $user->copy();
        $this->assertSame(8, $copy->getId());
        $this->assertTrue($user->delete());
        $this->assertSame(1, UsersEntity::count());
        $this->assertTrue($copy->delete());
        $this->assertSame(0, UsersEntity::count());
        $this->assertSame([], UsersEntity::all()->toArray());

        Items::create(['name' => 'foo', 'quantity' => 15, 'price' => 100]);
        Items::create(['name' => 'baz', 'quantity' => 20, 'price' => 150]);
        Items::create(['name' => 'bar', 'quantity' => 10, 'price' => 80]);

        $this->assertSame(30, Items::first()->getQuantity());

        Items::first()->setQuantity(10)->save();
        $this->assertSame(40, Items::first()->getQuantity());

        $items = new Dynamicmodel('test.item');

        $first = $items->model()->first();
        $this->assertSame(40, $first->getQuantity());
    }

    /**
     * @throws ReflectionException
     */
    public function testIterator()
    {
        $rows = [
            ['price' => 2],
            ['price' => 4],
            ['price' => 6],
            ['price' => 10]
        ];

        $iterator = new Iterator($rows, new TestIterator);
        $first = $iterator->first();
        $last = $iterator->last();

        $query = $iterator->where('price', '<', '10');

        $this->assertSame(4, $first['price']);
        $this->assertSame(20, $last['price']);
        $this->assertSame(4, $iterator->count());
        $this->assertSame(4, $query->first()['price']);
        $this->assertSame(8, $query->last()['price']);
        $this->assertSame(2, $query->count());
    }

    public function testFactor()
    {
        $gi = $this->factor(function () {
            $i = new TestFactor;
            $i->setTest('foo');

            return $i;
        });

        $this->assertInstanceOf(Instanciator::class, $gi);
        $this->assertSame($this->gi(), $gi);
        $this->assertSame('foo', $this->gi(TestFactor::class)->getTest());
        $this->assertSame('foo', $gi->get(TestFactor::class)->getTest());
    }

    public function testFlew()
    {
        $foo = $this->flew('foo', 15);
        $bar = $this->flew('bar');

        $this->assertNotSame($foo, $bar);
        $this->assertSame($foo, $this->flew('foo'));

        $foo['bar'] = 'baz';
        $bar['bar'] = 'foo';

        $this->assertSame('baz', $this->flew('foo')['bar']);
        $this->assertSame('foo', $this->flew('bar')['bar']);
        $this->assertNotSame($this->flew('foo')['bar'], $this->flew('bar')['bar']);

        $this->assertSame('baz', $foo->get('bar'));
        $this->assertSame('baz', $foo->getBar());
        $this->assertSame('baz', $foo->bar);
        $this->assertTrue($foo->has('bar'));
        $this->assertTrue($foo->hasBar());
        $foo->delete('bar');
        $this->assertFalse($foo->hasBar());
        $foo->set('bar', 'baz');
        $this->assertTrue($foo->hasBar());
        $foo->removeBar();
        $this->assertFalse($foo->hasBar());
        $foo->setBar('foo');
        $this->assertTrue($foo->hasBar());

        unset($foo['bar']);
        unset($bar['bar']);

        $this->assertNull($this->flew('foo')->bar);
        $this->assertNull($this->flew('bar')->bar);

        $foo['bar'] = 'baz';

        $foo->newDriver(Sessionarray::class);
        $this->assertSame('baz', $foo->bar);
    }

    public function testMake()
    {
        $this->assertSame($this->make_singleton(Inflector::class), $this->make_singleton(Inflector::class));
        $this->assertNotSame($this->make_new(stdClass::class), $this->make_new(stdClass::class));

        $this->assertNotSame(
            $this->make_factory(stdClass::class, function () {return new stdClass;}),
            $this->make_factory(stdClass::class)
        );

        $this->assertEquals(
            $this->make_factory(stdClass::class),
            $this->make_factory(stdClass::class)
        );

        $this->assertTrue($this->ifnmatch('eli*', 'élIse'));
    }

    /**
     * @throws ReflectionException
     */
    public function testComponent()
    {
        $app = new Component;
        $app->addClass(Mvc::class)['foo'] = 'bar';
        $this->assertSame('bar', $app['foo']);
        $this->assertSame('bar', $app->foo);
        $this->assertSame('bar', $app->foo());

        $app->test(function (Inflector $i, $a) {
            return $i::upper($a);
        });

        $app['baz'] = function ($x) {
            return 10 * $x;
        };

        $this->assertSame('FOO', $app->test('foo'));
        $this->assertSame('baz', $app->testing('BAZ'));
        $this->assertSame(100, $app->baz(10));
    }

    /**
     * @throws Exception
     */
    public function testLazyLoading()
    {
        $this->factorOnce(Inflector::class, function () {
            return new Inflector;
        });

        $this->assertSame($this->factorOnce(Inflector::class), $this->factorOnce(Inflector::class));

        $this->factorer(Arrays::class, function () {
            return new Arrays;
        });

        $this->assertNotSame($this->factorer(Arrays::class), $this->factorer(Arrays::class));

        $this->lazyCore('foo', 'bar');
        $this->assertSame('bar', $this->lazyCore('foo'));
        $this->assertSame('bar', $this->lazyCore('foo', 'baz'));
        $this->delCore('foo');
        $this->assertNull($this->lazyCore('foo'));
        $this->assertSame(1, $this->incrCore('bar'));
        $this->assertSame(10, $this->incrCore('bar', 9));
        $this->assertSame(9, $this->decrCore('bar'));
        $this->assertSame(1, $this->decrCore('bar', 8));
        $this->assertTrue($this->hasCore('bar'));

        $this->assertTrue($this->appli('foo', 'bar'));
        $this->assertSame('bar', $this->appli('foo'));

        $this->assertTrue($this->appli($this->gi()->singleton(Component::class)));
        $this->assertInstanceOf(Inflector::class, $this->appli(Inflector::class));

        $this->assertTrue($this->appli('i', function () {
            return $this->gi()->singleton(Inflector::class);
        }));

        $this->assertInstanceOf(Inflector::class, $this->appli('i'));
        $this->assertEquals($this->appli(Inflector::class), $this->appli('i'));

        $this->assertTrue($this->appli('baz', function ($a, $b = 0) {
            return $a + $b;
        }));
        $this->assertSame(10, $this->appli('baz', 5, 5));
        $this->assertSame(12, $this->appli('baz', 7, 5));
        $this->assertSame(7, $this->appli('baz', 7));

        $this->assertTrue($this->appli('bar', function (Inflector $i, $a): string {
            return $i::lower($a);
        }));
        $this->assertSame('baz', $this->appli('bar', 'BAZ'));
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function testThrottle()
    {
        /** @var Throttle $throttle */
        $throttle = $this->gi()->singleton(Throttle::class)->get('resource');

        $this->assertSame(0, $throttle->getAttempts());
        $status = $throttle->attempt();

        $this->assertTrue($status);
        $this->assertSame(1, $throttle->getAttempts());
        $status = $throttle->attempt(50);
        $this->assertSame(51, $throttle->getAttempts());
        $this->assertFalse($status);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function testYour()
    {
        $this->assertFalse(Your::has('foo'));

        Your::set('foo', 'bar');

        $this->assertTrue(Your::has('foo'));
        $this->assertSame('bar', Your::get('foo'));
        $this->assertSame(2, Your::incr('bar', 2));

        $your = Your::getInstance();

        $this->assertNull($your->baz);
        $this->assertNull($your['baz']);

        $your->baz = 'foo';
        $this->assertSame('foo', Your::get('baz'));
        $this->assertSame('foo', $your->baz);
        $this->assertSame('foo', $your['baz']);

        $your['baz'] = 'bar';
        $this->assertSame('bar', Your::get('baz'));
        $this->assertSame('bar', $your->baz);
        $this->assertSame('bar', $your['baz']);
        $this->assertSame('bar', $your->getBaz());
        $this->assertSame('bar', Your::getBaz());
    }

    /**
     * @throws ReflectionException
     * @throws TypeError
     */
    public function testYou()
    {
        You::setProvider('logout', function () {
            $session = You::getSession();

            unset($session[You::called()->getUserKey()]);

            return null;
        });

        You::setProvider('login', function () {
            $session = You::getSession();

            $session[You::called()->getUserKey()] = ['id' => 1, 'name' => 'foo', 'email' => 'foo@bar.com'];

            return true;
        });

        You::logout();

        $this->assertFalse(You::areAuth());
        $this->assertTrue(You::areGuest());

        You::login();

        $this->assertFalse(You::areGuest());
        $this->assertTrue(You::areAuth());
    }

    public function testRequest()
    {
        $request = $this->requestFromGlobals();

        $this->assertSame(\Octo\Parameter::class, get_class($request));
    }

    /**
     * @throws Exception
     */
    public function testLog()
    {
        $this->gi('Log', function () {
            return $this->gi(Memorylog::class);
        });

        $this->log('foo');

        $this->assertCount(1, Memorylog::all());

        $this->assertTrue(
            fnmatch(
                '*foo',
                $this->coll(Memorylog::all()['info'])->first())
            ? true : false
        );
    }

    /**
     * @throws Exception
     */
    public function testAlias()
    {
        $this->gi()->alias('foo', __CLASS__);
        $this->assertInstanceOf(__CLASS__, $this->gi()->get('foo'));
        $this->assertInstanceOf(Inflector::class, $this->gi(Inflector::class));
    }

    /**
     * @throws Exception
     */
    public function testTranslator()
    {
        /** @var \Octo\Trad $t */
        $t = $this->setTranslator(__DIR__ . '/lang', 'fr');

        $this->assertSame('maison', $t->get('test.home'));
        $this->assertSame('bar', $t->get('test.foo', ['name' => 'bar']));
    }

    /**
     * @throws Exception
     */
    public function testRemember()
    {
        Remember::set('foo', 'bar');

        $this->assertSame('bar', Remember::get('foo'));
    }

    /**
     * @throws Exception
     */
    public function testHelpers()
    {
        $this->assertSame("'foo'", $this->quoteString('foo'));
        $this->assertSame("'foo', 'bar'", $this->quoteString(['foo', 'bar']));
    }

    /**
     * @throws Exception
     */
    public function testOctoRenderer()
    {
        $html = $this->html(__DIR__ . '/views/demo', ['name' => 'foo']);

        $this->assertSame('<h1>Hello foo</h1>', $html);
    }

    /**
     * @throws Exception
     */
    public function testBladeRenderer()
    {
        $html = $this->blade(__DIR__ . '/blade/test', ['name' => 'foo']);

        $this->assertSame('<h1>Test foo</h1>', $html);
    }

    /**
     * @throws Exception
     */
    public function testTwigRenderer()
    {
        $html = $this->twig(__DIR__ . '/twig/hello', ['name' => 'foo']);

        $this->assertSame('<h1>Hello foo <a href="/slug/foo">link</a></h1>', $html);
    }

    /**
     * @throws Exception
     */
    public function testFinder()
    {
        $finder = new Finder();
        $finder->in(__DIR__)->date('<= now - 1 seconds');
        $this->assertGreaterThan(0, $finder->count());
        $this->assertInstanceOf(Generator::class, $finder->get());
    }

    public function testShare()
    {
        $this->setInstance($this->getPdo());

        $this->assertInstanceOf(PDO::class, $this->getInstance(PDO::class));
        $this->assertTrue($this->hasInstance(PDO::class));
        $this->delInstance(PDO::class);
        $this->assertFalse($this->hasInstance(PDO::class));
        $this->assertFalse($this->hasInstance(MyEvent::class));
        $this->assertInstanceOf(stdClass::class, $this->oneInstance(stdClass::class));
    }

    /**
     * @throws ReflectionException
     */
    public function testHasher()
    {
        $hasher = $this->getContainer()->hasher();

        $crypt = $hasher->make('test');

        $this->assertTrue($hasher->check('test', $crypt));
    }

    public function testFacade()
    {
        $this->assertSame(Inflector::upper('test'), Stringy::upper('test'));

        $this->makeFacade('Stringify', Inflector::class);

        $this->assertSame(Inflector::upper('test'), Stringify::upper('test'));
    }

    /**
     * @throws ReflectionException
     */
    public function testNotification()
    {
        $post = new PostNotify;
        $post->notify(Notifier::class);

        $this->assertEquals(1, Alert::count());

        $notification = Alert::first();

        $this->assertSame($post->toArray(), unserialize($notification->data));

        $post->notify(Notifier::class);

        $this->assertEquals(2, Alert::count());
    }

    public function testMonkey()
    {
        /** @var Monkeypatch $patch */
        $patch =  $this->monkeyPatch('App', 'strlen', function ($cobcerb) {
            return strlen($cobcerb) * 3;
        });

        $patch->enable();

        $this->assertSame(3, \App\strlen('a'));

        $patch->disable();

        $this->assertSame(1, \App\strlen('a'));
    }

    public function testBreeze()
    {
        $breeze = new Breeze();
        $breeze->set('foo', 'bar');
        $this->assertSame('bar', $breeze->get('foo'));
    }

    public function testProxify()
    {
        $proxy = new Proxify(Subscriber::class);

        $proxy->_override('getEvents', function () {
            return 1;
        });

        $this->assertSame(1, $proxy->getEvents());
        $this->assertSame(1, $proxy->_called('getEvents'));
    }

    /**
     * @throws Exception
     */
    public function testMakeResource()
    {
        $resource = $this->makeResource(new Subscriber);

        $this->assertTrue(is_resource($resource));

        $this->assertInstanceOf(Subscriber::class, $this->makeFromResource($resource));
    }
    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function testTrust()
    {
        $this->getEventManager()->on('trust.login.auth', function (bool $status, Live $session) {
            $session['trust.login.event'] = $status;
        });

        $this->getEventManager()->on('trust.logout.auth', function (bool $status, Live $session) {
            $session['trust.logout.event'] = $status;
        });

        /** @var Live $session */
        $session = MyGuard::session();

        $session['trust.login.event'] = false;
        $session['trust.logout.event'] = false;

        $this->assertFalse(MyGuard::isAuth());
        $this->assertTrue(MyGuard::isGuest());

        $this->assertFalse(MyGuard::session()['trust.login.event']);
        $this->assertFalse(MyGuard::session()['trust.logout.event']);

        MyGuard::policy('foo', function ($user) {
            return $user['id'] === 1;
        });

        MyGuard::login();

        $can = MyGuard::can('foo');
        $this->assertTrue($can);

        $this->assertTrue(MyGuard::session()['trust.login.event']);

        $this->assertTrue(MyGuard::isAuth());
        $this->assertFalse(MyGuard::isGuest());

        $this->assertSame(1,                MyGuard::user('id'));
        $this->assertSame('foo',            MyGuard::user('name'));
        $this->assertSame('foo@bar.com',    MyGuard::user('email'));

        MyGuard::logout();

        $this->assertTrue(MyGuard::session()['trust.logout.event']);

        $this->assertFalse(MyGuard::isAuth());
        $this->assertTrue(MyGuard::isGuest());

        MyGuard::login();

        $guard = MyGuard::forUser(['id' => 2, 'name' => 'bar', 'email' => 'bar@foo.com']);

        $this->assertSame(2,                $guard->user('id'));
        $this->assertSame('bar',            $guard->user('name'));
        $this->assertSame('bar@foo.com',    $guard->user('email'));

        $can = MyGuard::can('foo');
        $this->assertFalse($can);

        MyGuard::recoverUser();

        $this->assertSame(1,                MyGuard::user('id'));
        $this->assertSame('foo',            MyGuard::user('name'));
        $this->assertSame('foo@bar.com',    MyGuard::user('email'));
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws \Octo\Exception
     */
    public function testLive()
    {
        $this->getEventManager()->on('live.login', function (bool $status, Live $session, $request) {
            $session['live.login.event'] = $status;
        });

        $this->getEventManager()->on('live.logout', function (bool $status, Live $session, $request) {
            $session['live.logout.event'] = $status;
        });

        $live = new Live(new Now(sessionKey()));

        $live->setLoginProvider(function ($session, $request) {
            $session['user'] = ['id' => 1, 'name' => 'foo', 'email' => 'foo@bar.com'];

            return true;
        });

        $live->setLogoutProvider(function ($session, $request) {
            unset($session['user']);

            return true;
        });

        $live->destroy();

        $live['foo'] = 'bar';
        $live['live.login.event'] = false;
        $live['live.logout.event'] = false;

        $this->assertSame('bar', $live['foo']);
        $this->assertFalse($live->isAuth());
        $this->assertTrue($live->isGuest());

        $this->assertFalse($live['live.login.event']);
        $this->assertFalse($live['live.logout.event']);

        $live->login();

        $this->assertTrue($live['live.login.event']);
        $this->assertSame(1, $live->user('id'));
        $this->assertSame('foo', $live->user('name'));
        $this->assertSame('foo@bar.com', $live->user('email'));

        $this->assertTrue($live->isAuth());
        $this->assertFalse($live->isGuest());

        $live->logout();

        $this->assertTrue($live['live.logout.event']);
        $this->assertFalse($live->isAuth());
        $this->assertTrue($live->isGuest());
    }

    /**
     * @throws Exception
     */
    public function testContainer()
    {
        Bag::test(function (Bag $container, Inflector $i) {
            return $i->upper($container->getDummy());
        });

        $this->assertSame('DUMMY', Bag::test());
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_is_a_basic_test()
    {
        $theme = $this->em('theme')->store(['name' => 'biopic']);

        $post = $this->em('post')->store([
            'theme_id' => (int) $theme->id
        ]);

        $this->assertEquals($post->theme_id, $theme->id);
        $this->assertEquals($post->theme()->id, $theme->id);
        $this->assertEquals($post->theme->id, $theme->id);
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_tests_path()
    {
        $this->assertEquals(__DIR__, $this->path('app'));
        $this->assertEquals(__DIR__ . DS . 'storage', $this->path('storage'));
    }

    /**
     * @test
     * @throws Exception
     * @throws ReflectionException
     */
    public function static_fire_class()
    {
        $this->app['event_test_value'] = 0;

        $this->assertTrue(MyEvent::called() instanceof Octo\Fire);
        $this->assertTrue(MyEvent::called() instanceof MyEvent);
        $this->assertEquals('myevent', MyEvent::called()->ns());

        $this->assertEquals(0, count(Registry::get('fire.events.MyEvent', [])));

        MyEvent::listen('test', function () {
            $this->app['event_test_value'] += 2;
        });

        $this->assertEquals(1, count(Registry::get('fire.events.myevent', [])));

        MyEvent::fire('test');

        $this->assertEquals(2, $this->app['event_test_value']);

        MyEvent::fire('test');

        $this->assertEquals(4, $this->app['event_test_value']);

        MyEvent::subscribe(Subscriber::class);

        $this->app['event_test_value'] = 0;

        MyEvent::fire('test_1');
        MyEvent::fire('test_2');

        $this->assertEquals(3, $this->app['event_test_value']);
    }

    /**
     * @@test
     * @throws Exception
     * @throws ReflectionException
     */
    public function internal_fire_class()
    {
        $this->app['event_test_value'] = 0;

        $this->assertTrue(InternalEvents::called() instanceof Octo\Fire);
        $this->assertTrue(InternalEvents::called() instanceof InternalEvents);
        $this->assertEquals('octo-internalevents', InternalEvents::called()->ns());

        $this->assertEquals(0, count(Registry::get('fire.events.Octo\InternalEvents', [])));

        On::test(function () {
            $this->app['event_test_value'] += 2;
        });

        $this->assertEquals(1, count(Registry::get('fire.events.octo-internalevents', [])));

        Emit::test();

        $this->assertEquals(2, $this->app['event_test_value']);

        Emit::test();

        $this->assertEquals(4, $this->app['event_test_value']);
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_burst()
    {
        $burst = $this->burst('/assets/css/style.css');

        $hash = md5(filemtime(__DIR__ . '/assets/css/style.css'));

        $this->assertEquals('/burst/assets/css/style-' . $hash . '.css', $burst);
    }

    /**
     * @test
     * @throws Exception
     */
    public function containerTests()
    {
        $this->container('test', 1);

        $this->container(Datetime::class, function () {
            return $this->foundry(stdClass::class);
        });

        $this->assertEquals(1, $this->container('test'));
        $this->assertEquals(stdClass::class, get_class($this->container(Datetime::class)));
        $this->assertEquals(stdClass::class, get_class($this->foundry(Datetime::class)));
        $this->assertEquals($this->maker(Datetime::class), $this->container(Datetime::class));
    }

    /**
     * @test
     * @throws Exception
     */
    public function mail()
    {
        $message = Octo\message()
        ->from('test@test.com')
        ->to('test@test.com')
        ->subject('test')
        ->attach(__DIR__ . '/mail.phtml')
        ->view(__DIR__ . '/mail.phtml', ['name' => 'John Doe']);

        $this->assertContains('<h1>This is a mail view !!</h1>', $message->getBody());
        $this->assertContains('<h2>For John Doe</h2>', $message->getBody());

        $send = isAke($_SERVER, 'HOME', null) === '/home/octo';

        if ($send) {
            Config::set('MAILER_DRIVER', 'smtp');
            Config::set('SMTP_SECURITY', '');
            Config::set('SMTP_PORT', 1025);

            $status = $message->send();

            $this->assertEquals(1, $status);
        }
    }
}
