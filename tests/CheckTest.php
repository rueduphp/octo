<?php
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
    }
