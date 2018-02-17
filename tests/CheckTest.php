<?php
    use Octo\Checking;
    use Octo\Inflector;

    class CheckTest extends TestCase
    {
        /**
         * @test
         * @throws Exception
         */
        public function checkingIsSuccess()
        {
            $data = [
                'lastname'  => 'Doe',
                'firstname' => 'John',
                'email'     => 'JohnDoe@doe.com',
                'slug'      => Inflector::urlize('this is a slug', '-'),
                'age'       => 35
            ];

            /** @var Checking $validator */
            $validator = $this->lib('checking', [$data]);

            $validator->add('lastname')->maxLength(10);

            $validator->add('firstname')->minLength(3);

            $validator->add('email')->required()->email();
            $validator->add('slug')->required()->slug();

            $validator->add('age')
            ->required()
            ->integer()
            ->custom(function ($field, $value) {
                return 18 <= $value;
            });

            $validator->validate();

            $this->assertTrue($validator->success());
        }

        /**
         * @test
         * @throws Exception
         */
        public function checkingIsFail()
        {
            $data = [
                'lastname'  => 'Doe',
                'firstname' => '',
                'email'     => 'JohnDoe@doe.com',
                'slug'      => Inflector::urlize('this is a slug', '?'),
                'age'       => 35
            ];

            /** @var Checking $validator */
            $validator = $this->lib('checking', [$data]);

            $validator->add('lastname')->maxLength(10);

            $validator->add('firstname')->required()->notEmpty()->minLength(10);

            $validator->add('email')->required()->email();
            $validator->add('slug')->required()->slug();

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
            $this->assertCount(1, $validator->getErrors()['age']);

            $this->assertSame(
                'firstname is empty',
                (string) current($validator->getErrors()['firstname'])
            );

            $this->assertSame(
                'firstname is too short',
                (string) end($validator->getErrors()['firstname'])
            );
        }
    }
