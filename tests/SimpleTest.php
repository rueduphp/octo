<?php

use Octo\Alert;
use Octo\Breeze;
use Octo\Config;
use Octo\Emit;
use Octo\Inflector;
use Octo\InternalEvents;
use Octo\Live;
use Octo\Monkeypatch;
use Octo\Now;
use Octo\On;
use Octo\Proxify;
use Octo\Registry;
use function Octo\sessionKey;
use Octo\Trust;

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

class PostNotify
{
    use \Octo\Notifiable;

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

class Middleware
{
    public function handle($request, $response, callable $next)
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

        $request = context('app')->request();

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

class SimpleTest extends TestCase
{
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

        MyGuard::login();

        $this->assertTrue(MyGuard::session()['trust.login.event']);

        $this->assertTrue(MyGuard::isAuth());
        $this->assertFalse(MyGuard::isGuest());

        $this->assertSame(1, MyGuard::user('id'));
        $this->assertSame('foo', MyGuard::user('name'));
        $this->assertSame('foo@bar.com', MyGuard::user('email'));

        MyGuard::logout();

        $this->assertTrue(MyGuard::session()['trust.logout.event']);

        $this->assertFalse(MyGuard::isAuth());
        $this->assertTrue(MyGuard::isGuest());

        MyGuard::login();

        $guard = MyGuard::forUser(['id' => 2, 'name' => 'bar', 'email' => 'bar@foo.com']);

        $this->assertSame(2, $guard->user('id'));
        $this->assertSame('bar', $guard->user('name'));
        $this->assertSame('bar@foo.com', $guard->user('email'));

        MyGuard::recoverUser();

        $this->assertSame(1, MyGuard::user('id'));
        $this->assertSame('foo', MyGuard::user('name'));
        $this->assertSame('foo@bar.com', MyGuard::user('email'));
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
        $message = $this->message()
        ->from('test@test.com')
        ->to('qa@test.com')
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
