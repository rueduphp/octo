<?php
namespace App\Factories;

use Faker\Generator as Faker;
use Octo\FastFactory;

class User 
{
    /**
     * @throws \Exception
     */
    public function __construct()
    {
        FastFactory::add(\App\Models\User::class, function (Faker $faker) {
            return [
                "name"      => $faker->name,
                "email"     => $faker->email,
                "password"  => encrypt('000000')
            ];
        });
    }
}
