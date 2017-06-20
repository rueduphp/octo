<?php
    use Octo\Registry;

    class MyEvent extends Octo\Fire {}

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
        }
    }
