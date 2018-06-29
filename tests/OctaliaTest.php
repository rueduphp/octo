<?php

use Octo\ActiveRecord as AR;
use Octo\Config;
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

    /**
     * @return bool
     */
    private function factories()
    {
        Factory::for(PostEntity::class, function ($faker) {
            return [
                'content'   => $t = $faker->sentence,
                'user_id'   => $u = rand(1, 10),
                'title'     => Strings::urlize($t)
            ];
        });

        return true;
    }

    public function tearDown()
    {
        parent::tearDown();

        Config::set('octalia.engine', $this->engine);
    }

    /**
     * @test
     * @throws Exception
     */
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
        $this->assertSame(1, $user->getKey());
    }

    /**
     * @test
     * @throws ReflectionException
     */
    public function invoke()
    {
        $this->addUser();
        $this->addUser();

        $db = new UserEntity;

        $this->assertSame(UserEntity::find(2)->toArray(), $db(2)->toArray());
    }

    /**
     * @test
     * @throws Exception
     */
    public function scope()
    {
        $this->addUser();

        $this->assertEquals(1, UserEntity::test(0)->count());
        $this->assertEquals(
            UserEntity::where('id', '>', 1)->count(),
            UserEntity::test(1)->count()
        );

        $this->assertEquals(1, UserEntity::where(function ($row) {
            return $row["id"] > 0;
        })->count());
    }

    /**
     * @test
     * @throws Exception
     */
    public function attributes()
    {
        $user = $this->addUser();

        $user->test = 15;

        $this->assertEquals(20, $user->test);

        unset($user->test);

        $this->assertEquals(10, $user->test);
    }

    /**
     * @throws Exception
     */
    public function testFactories()
    {
        Factory::save(PostEntity::class, 15);
        $this->assertEquals(15, PostEntity::count());
    }

    /**
     * @throws \Octo\Exception
     */
    public function testRelations()
    {
        $this->addUsers(20);
        $this->addPosts(20);

        $this->assertEquals(20, UserEntity::count());
        $this->assertSame(1,    UserEntity::min("id"));
        $this->assertSame(20,   UserEntity::max("id"));
        $this->assertSame(10.5, UserEntity::avg("id"));
        $this->assertSame(1,    UserEntity::first()->getKey());
        $this->assertSame(20,   UserEntity::last()->getKey());

        $this->assertEquals(PostEntity::first()->user(), UserEntity::find(20));
        $this->assertEquals(PostEntity::first(), UserEntity::find(20)->posts()->first());
    }

    /**
     * @param array $data
     *
     * @return mixed
     */
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

    /**
     * @param int $times
     */
    private function addUsers(int $times = 2)
    {
        $faker = $this->faker();

        for ($i = 1; $i <= $times; $i++) {
            $user = [
                'email' => $faker->email
            ];

            $this->addUser($user);
        }
    }

    /**
     * @param int $times
     */
    private function addPosts(int $times = 2)
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
}
