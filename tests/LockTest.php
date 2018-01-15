<?php

use Faker\Generator as Faker;
use Octo\App;
use Octo\Fast;
use Octo\FastAuth;
use Octo\FastFactory;
use Octo\FastGate;
use Octo\Lock;
use Tests\Migrations;
use Tests\Post;

class MyPost extends Octo\Octal {}

class CustomAuth extends FastAuth
{
    /**
     * @param Lock $instance
     */
    protected function setUp(Lock $instance)
    {
        $instance->setLoginPath('/login');
        $instance->setLogoutPath('/logout');
    }

    /**
     * @param Lock $instance
     * @param $user
     * @param $password
     *
     * @return bool
     *
     * @throws TypeError
     */
    protected function login(Lock $instance, $user, $password)
    {
        if ($user === 'user' && $password === 'password') {
            $session = $instance->getSession();
            $user = $session[$instance->getKey()] = ['id' => 1, 'name' => 'John Doe'];

            return $user;
        }

        return false;
    }

    /**
     * @param Lock $instance
     *
     * @return bool
     *
     * @throws TypeError
     */
    protected function isAuth(Lock $instance)
    {
        $session = $instance->getSession();

        return isset($session[$instance->getKey()]);
    }

    /**
     * @param Lock $instance
     *
     * @return bool
     *
     * @throws TypeError
     */
    protected function logout(Lock $instance)
    {
        $session = $instance->getSession();

        if (isset($session[$instance->getKey()])) {
            unset($session[$instance->getKey()]);

            return true;
        }

        return false;
    }
}

class LockTest extends TestCase
{
    /**
     * @var Fast
     */
    protected $app;

    public function setUp()
    {
        parent::setUp();

        $app = $this->app = App::create();

        $app->handle(Lock::class, CustomAuth::class);

        $PDOoptions = [
            PDO::ATTR_CASE                 => PDO::CASE_NATURAL,
            PDO::ATTR_ERRMODE              => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_ORACLE_NULLS         => PDO::NULL_NATURAL,
            PDO::ATTR_DEFAULT_FETCH_MODE   => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES    => false,
            PDO::ATTR_EMULATE_PREPARES     => false
        ];

        $this->pdo = new PDO('sqlite::memory:', null, null, $PDOoptions);

        $this->db = new Orm($this->pdo);

        Migrations::migrate($this->db->schema());
    }

    /**
     * @throws TypeError
     */
    public function testStatic()
    {
        $status = Lock::connect('user', 'password');

        $this->assertTrue($status);

        $this->assertSame(['id' => 1, 'name' => 'John Doe'], Lock::get());
        $this->assertSame(1, Lock::get('id'));
        $this->assertSame('John Doe', Lock::get('name'));

        $this->assertTrue(Lock::isAuth());

        $status = Lock::disconnect();

        $this->assertTrue($status);
        $this->assertFalse(Lock::isAuth());

        $status = Lock::disconnect();

        $this->assertFalse($status);

        $this->assertSame('/login', Lock::loginPath());
        $this->assertSame('/logout', Lock::logoutPath());
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function testPerms()
    {
        FastGate::rule('update_post', function (array $user, int $id) {
            return $user['id'] === $id;
        });

        FastGate::rule('delete_post', function (array $user, int $id) {
            return $user['id'] !== $id;
        });

        Lock::connect('user', 'password');

        $this->assertTrue(FastGate::can('update_post', 1));
        $this->assertFalse(FastGate::cannot('update_post', 1));

        $this->assertFalse(FastGate::can('delete_post', 1));
        $this->assertTrue(FastGate::cannot('delete_post', 1));

        FastGate::authorize('delete_post');

        $this->assertTrue(FastGate::can('delete_post'));
        $this->assertFalse(FastGate::cannot('delete_post'));

        FastGate::forbid('update_post');

        $this->assertFalse(FastGate::can('update_post'));
        $this->assertTrue(FastGate::cannot('update_post'));

        $this->assertTrue(FastGate::isAuth());
        $this->assertFalse(FastGate::isGuest());

        $this->assertSame(FastGate::id(), Lock::get('id'));
    }

    /**
     * @throws Exception
     */
    public function testFactories()
    {
        FastFactory::add(UserModel::class, function (Faker $faker) {
            return [
                "name" => $faker->name
            ];
        });

        FastFactory::add(Post::class, function (Faker $faker) {
            return [
                "content"   => $faker->paragraph,
                "title"     => $faker->title,
                "user_id"   => $faker->randomDigit
            ];
        });

        FastFactory::add(MyPost::class, function (Faker $faker) {
            return [
                "content"   => $faker->paragraph,
                "title"     => $faker->title,
                "user_id"   => $faker->randomDigit
            ];
        });

        $this->assertCount(5, UserModel::factory()->make(5));
        $this->assertCount(6, Post::factory()->make(6));
        $this->assertCount(9, MyPost::factory()->make(9));

        $rows = UserModel::factory()->create(5);
        $this->assertEquals(5, (int) UserModel::count());
        $this->assertCount(5, $rows);

        $rows = Post::factory()->create(8, ["title" => 'test']);
        $this->assertEquals("test", Post::first()->title);
        $this->assertEquals(8, (int) Post::count());
        $this->assertCount(8, $rows);

        $rows = MyPost::factory()->create(10);
        $this->assertEquals(10, (int) MyPost::count());
        $this->assertCount(10, $rows);
    }
}