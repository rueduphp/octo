<?php
    use Octo\Checking;

    class CheckTest extends TestCase
    {
        /** @test */
        public function checkingIsSuccess()
        {
            $data = [
                'lastname'  => 'Doe',
                'firstname' => 'John',
                'email'     => 'JohnDoe@doe.com',
                'age'       => 35
            ];

            /** @var Checking $validator */
            $validator = $this->lib('checking', [$data]);

            $validator->add('lastname')->maxLength(10);

            $validator->add('firstname')->minLength(3);

            $validator->add('email')->required()->email();

            $validator->add('age')
            ->required()
            ->integer()
            ->custom(function ($field, $value) {
                return 18 <= $value;
            });

            $validator->validate();

            $this->assertTrue($validator->success());
        }
        /** @test */
        public function checkingIsFail()
        {
            $data = [
                'lastname'  => 'Doe',
                'firstname' => '',
                'email'     => 'JohnDoe@doe.com',
                'age'       => 35
            ];

            /** @var Checking $validator */
            $validator = $this->lib('checking', [$data]);

            $validator->add('lastname')->maxLength(10);

            $validator->add('firstname')->required()->minLength(10);

            $validator->add('email')->required()->email();

            $validator->add('age')
            ->required()
            ->integer()
            ->custom(function ($field, $value) {
                return 50 <= $value;
            });

            $validator->validate();

            $this->assertFalse($validator->success());
            $this->assertTrue($validator->fail());

            $this->assertCount(2, $validator->getErrors()['firstname']);

            $this->assertEquals(
                'firstname is required',
                (string) current($validator->getErrors()['firstname'])
            );

            $this->assertEquals(
                'firstname is too short',
                (string) end($validator->getErrors()['firstname'])
            );
        }
    }
