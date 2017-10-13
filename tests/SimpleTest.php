<?php
    use Octo\Registry;
    use Octo\Config;
    use Octo\InternalEvents;
    use Octo\On;
    use Octo\Emit;
    use Octo\Dispatch;

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
        /** @test */
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

        /** @test */
        public function it_tests_path()
        {
            $this->assertEquals(__DIR__, $this->path('app'));
            $this->assertEquals(__DIR__ . DS . 'storage', $this->path('storage'));
        }

        /** @test */
        public function static_fire_class()
        {
            $this->app['event_test_value'] = 0;

            $this->assertTrue(MyEvent::called() instanceof Octo\Fire);
            $this->assertTrue(MyEvent::called() instanceof MyEvent);
            $this->assertEquals('MyEvent', MyEvent::called()->ns());

            $this->assertEquals(0, count(Registry::get('fire.events.MyEvent', [])));

            MyEvent::listen('test', function () {
                $this->app['event_test_value'] += 2;
            });

            $this->assertEquals(1, count(Registry::get('fire.events.MyEvent', [])));

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

        /** @test */
        public function internal_fire_class()
        {
            $this->app['event_test_value'] = 0;

            $this->assertTrue(InternalEvents::called() instanceof Octo\Fire);
            $this->assertTrue(InternalEvents::called() instanceof InternalEvents);
            $this->assertEquals('Octo\InternalEvents', InternalEvents::called()->ns());

            $this->assertEquals(0, count(Registry::get('fire.events.Octo\InternalEvents', [])));

            On::test(function () {
                $this->app['event_test_value'] += 2;
            });

            $this->assertEquals(1, count(Registry::get('fire.events.Octo\InternalEvents', [])));

            Emit::test();

            $this->assertEquals(2, $this->app['event_test_value']);

            Emit::test();

            $this->assertEquals(4, $this->app['event_test_value']);
        }

        /** @test */
        public function it_burst()
        {
            $burst = $this->burst('/assets/css/style.css');

            $hash = md5(filemtime(__DIR__ . '/assets/css/style.css'));

            $this->assertEquals('/burst/assets/css/style-' . $hash . '.css', $burst);
        }

        /** @test */
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

        /** @test */
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

            $send = isAke($_SERVER, 'HOME', null) !== '/home/gerald';

            if ($send) {
                Config::set('MAILER_DRIVER', 'smtp');
                Config::set('SMTP_SECURITY', '');
                Config::set('SMTP_PORT', 1025);

                $status = $message->send();

                $this->assertEquals(1, $status);
            }
        }
    }
