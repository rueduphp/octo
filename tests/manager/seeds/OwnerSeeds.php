<?php
    use Phinx\Seed\AbstractSeed;
    use function Octo\faker;

    class OwnerSeeds extends AbstractSeed
    {
        public function run()
        {
            $faker = faker();

            $orm = new Orm;

            $orm->into('owner')->builder()->insert(['name' => $faker->name]);

            for ($i = 0; $i < 50; $i++) {
                $row = [
                    'name' => $faker->name
                ];

                $orm->insert($row)->into('owner')->run();
            }
        }
    }
