<?php
    use Octo\Config;
    use Octo\ActiveRecord as AR;
    use Octo\Factory;

    class PostEntity extends Octo\Octal {}
    class UserEntity extends Octo\Octal
    {
        public function setAttributeTest($value, $record)
        {
            return 5 + $value;
        }

        public function getAttributeTest($record)
        {
            return 10;
        }

        public function scopeTest($query, $max)
        {
            return $query->where('id', '>', $max);
        }
    }

    class OctaliaTest extends TestCase
    {
        private $engine;

        public function setUp()
        {
            parent::setUp();

            $this->engine = $this->conf('octalia.engine');
            Config::set('octalia.engine', 'ndb');

            UserEntity::drop();
            PostEntity::drop();

            $this->factories();
        }

        private function factories()
        {
            Factory::for(PostEntity::class, function ($faker) {
                return [
                    'content'   => $t = $faker->sentence,
                    'user_id'   => $u = rand(1, 10),
                    'title'     => Strings::urlize($t)
                ];
            });
        }

        public function tearDown()
        {
            parent::tearDown();

            Config::set('octalia.engine', $this->engine);
        }

        /** @test */
        public function entity()
        {
            /**
             * @var AR $user
             */
            $user = $this->addUser();

            $this->assertEquals('123456', $user->password);

            $user->setPassword('123456')->save();

            $this->assertEquals(1, UserEntity::count());
            $this->assertTrue('123456' !== $user->password);
        }

        /** @test */
        public function invoke()
        {
            $this->addUser();
            $this->addUser();

            $db = new UserEntity;

            $this->assertEquals(UserEntity::find(2)->toArray(), $db(2)->toArray());
        }

        /** @test */
        public function scope()
        {
            $user = $this->addUser();

            $this->assertEquals(1, UserEntity::test(0)->count());
            $this->assertEquals(
                UserEntity::where('id', '>', 1)->count(),
                UserEntity::test(1)->count()
            );
        }

        /** @test */
        public function attributes()
        {
            $user = $this->addUser();

            $user->test = 15;

            $this->assertEquals(20, $user->test);

            unset($user->test);

            $this->assertEquals(10, $user->test);
        }

        public function testRelations()
        {
            $this->addUsers(20);
            $this->addPosts(20);

            $this->assertEquals(20, UserEntity::count());
            $this->assertSame(1, UserEntity::min("id"));
            $this->assertSame(20, UserEntity::max("id"));
            $this->assertSame(10.5, UserEntity::avg("id"));
            $this->assertSame(1, UserEntity::first()->getId());
            $this->assertSame(20, UserEntity::last()->getId());

            $this->assertEquals(PostEntity::first()->user(), UserEntity::find(20));
            $this->assertEquals(PostEntity::first(), UserEntity::find(20)->posts()->first());
        }

        private function addUser(array $data = [])
        {
            if (empty($data)) {
                $data = [
                    'firstname' => 'John',
                    'name'      => 'Doe',
                    'password'  => '123456',
                    'email'     => 'johndoe@email.com'
                ];
            }

            return UserEntity::store($data);
        }

        private function addUsers($times = 2)
        {
            $faker = $this->faker();

            for ($i = 1; $i <= $times; $i++) {
                $user = [
                    'email' => $faker->email
                ];

                $this->addUser($user);
            }
        }

        private function addPosts($times = 2)
        {
            $faker = $this->faker();

            for ($i = 1; $i <= $times; $i++) {
                $post = [
                    'user_id' => ($times + 1) - $i,
                    'slug'    => $faker->slug
                ];

                PostEntity::store($post);
            }
        }

        public function testFactories()
        {
            Factory::save(PostEntity::class, 15);
            $this->assertEquals(15, PostEntity::count());
        }
    }
