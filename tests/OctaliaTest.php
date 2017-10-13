<?php
    use Octo\Config;

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

            $this->engine = Octo\conf('octalia.engine');
            Config::set('octalia.engine', 'ndb');
            UserEntity::drop();
        }

        public function tearDown()
        {
            parent::tearDown();

            Config::set('octalia.engine', $this->engine);
        }

        /** @test */
        public function entity()
        {
            $user = $this->addUser();

            $this->assertEquals('123456', $user->password);

            $user->setPassword('123456')->save();

            $this->assertEquals(1, UserEntity::count());
            $this->assertTrue('123456' !== $user->password);
        }

        /** @test */
        public function invoke()
        {
            $user = $this->addUser();
            $user = $this->addUser();

            $db = new UserEntity;

            $this->assertEquals(UserEntity::find(2)->toArray(), $db(2)->toArray());
        }

        /** @test */
        public function scope()
        {
            $user = $this->addUser();

            $this->assertEquals(1, UserEntity::test(0)->count());
            $this->assertEquals(UserEntity::where('id', '>', 1)->count(), UserEntity::test(1)->count());
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
    }
