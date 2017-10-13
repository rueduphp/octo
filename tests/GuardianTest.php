<?php
    use Octo\Authentication;
    use Octo\Guardian;

    class GuardianTest extends TestCase
    {
        public function setUp()
        {
            parent::setUp();

            $db = $this->em('user');

            $this->user = $db->store([
                'login' => 'user',
                'role' => 'admin'
            ]);

            Authentication::login($this->user);
        }

        /** @test */
        public function checkPolicy()
        {
            $this->context('app')->test_value = 0;

            Guardian::policy('test', function ($user, $a, $b) {
                $this->context('app')->test_value = $b;

                return $user->login == 'user' && $user->role == 'admin' && $a > 4;
            });

            $can = Guardian::can('test', 5, 'b');

            $this->assertTrue($can);
            $this->assertEquals('b', $this->context('app')->test_value);
        }
    }
