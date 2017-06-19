<?php
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
    }
