<?php
    use Octo\Registry;
    use function Octo\context;

    class MyEvent extends Octo\Fire {}

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
        public function it_burst()
        {
            $burst = $this->burst('/assets/css/style.css');

            $hash = md5(filemtime(__DIR__ . '/assets/css/style.css'));

            $this->assertEquals('/burst/assets/css/style-' . $hash . '.css', $burst);
        }
    }
