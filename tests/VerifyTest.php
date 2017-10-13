<?php
    class VerifyTest extends TestCase
    {
        public function testRequired()
        {
            $verify = $this->lib('verify', [['name' => 'test', 'content' => 'test']]);

            $errors = $verify->required('name', 'content')->getErrors();

            $this->assertCount(0, $errors);

            $verify = $this->lib('verify', [['name' => '', 'content' => 'test']]);

            $errors = $verify->required('name', 'content')->getErrors();

            $this->assertCount(0, $errors);

            $verify = $this->lib('verify', [['content' => 'test']]);

            $errors = $verify->required('name', 'content')->getErrors();

            $this->assertCount(1, $errors);
        }

        public function testNotEmpty()
        {
            $verify = $this->lib('verify', [['name' => 'test', 'content' => '']]);

            $errors = $verify->notEmpty('name', 'content')->getErrors();

            $this->assertCount(1, $errors);
            $this->assertFalse($verify->success());
            $this->assertTrue($verify->fail());
        }

        public function testSlug()
        {
            $verify = $this->lib('verify', [[
                'slug1' => 'aze-as_1',
                'slug2' => 'aze-As_1',
                'slug3' => 'aze--as-1',
                'slug4' => 'aze-as-1'
            ]]);

            $errors = $verify->slug('slug1', 'slug2', 'slug3', 'slug4', 'slug5')->getErrors();

            $this->assertCount(3, $errors);
            $this->assertFalse($verify->success());
            $this->assertTrue($verify->fail());
        }

        public function testLength()
        {
            $params = ['test' => '123456789'];
            $verify = $this->lib('verify', [$params]);

            $errors = $verify->length('test', 15)->getErrors();

            $this->assertCount(1, $errors);

            $verify = $this->lib('verify', [$params]);

            $errors = $verify->length('test', 1, 3)->getErrors();

            $this->assertCount(1, $errors);

            $verify = $this->lib('verify', [$params]);

            $errors = $verify->length('test', 3, 20)->getErrors();

            $this->assertCount(0, $errors);
            $this->assertFalse($verify->fail());
            $this->assertTrue($verify->success());

            $verify = $this->lib('verify', [$params]);

            $errors = $verify->length('test', null, 20)->getErrors();

            $this->assertCount(0, $errors);

            $verify = $this->lib('verify', [$params]);

            $errors = $verify->length('test', null, 6)->getErrors();

            $this->assertCount(1, $errors);

            $verify = $this->lib('verify', [['test' => 123456789]]);

            $errors = $verify->string('test')->getErrors();

            $this->assertCount(1, $errors);
        }

        public function testCustom()
        {
            $callable = function ($value) {
                return $value >= 18;
            };

            $params = ['age' => 25];

            $verify = $this->lib('verify', [$params]);

            $errors = $verify->custom($callable, 'age')->getErrors();

            $this->assertCount(0, $errors);

            $params = ['age' => 16];

            $verify = $this->lib('verify', [$params]);

            $errors = $verify->custom($callable, 'age')->getErrors();

            $this->assertCount(1, $errors);
        }

        public function testEmail()
        {
            $params = ['email' => 'test@test.com'];

            $verify = $this->lib('verify', [$params]);

            $errors = $verify->email('email')->getErrors();

            $this->assertCount(0, $errors);

            $params = ['email' => 'test@test'];

            $verify = $this->lib('verify', [$params]);

            $errors = $verify->email('email')->getErrors();

            $this->assertCount(1, $errors);
        }

        public function testDatetime()
        {
            $params = ['updated_at' => '2017-09-12 09:10:10'];

            $verify = $this->lib('verify', [$params]);

            $errors = $verify->datetime('updated_at')->getErrors();

            $this->assertCount(0, $errors);

            $params = ['updated_at' => '2017-09-12'];

            $verify = $this->lib('verify', [$params]);

            $errors = $verify->datetime('updated_at')->getErrors();

            $this->assertCount(1, $errors);
        }
    }
