<?php
    use Phinx\Seed\AbstractSeed;
    use Octo\Strings;
    use function Octo\dd;
    use function Octo\faker;
    use function Octo\context;

    class PostSeeds extends AbstractSeed
    {
        public function run()
        {
            $faker = faker();

            $app = context('app');

            $orm = new Orm($app->pdo);

            $data = [];

            for ($i = 0; $i < 10; $i++) {
                $row = [
                    'content' => $t = $faker->sentence,
                    'user_id' => $u = rand(1, 9),
                    'title' => Strings::urlize($t)
                ];

                $orm->insert($row)->into('post')->run();

                $orm->insert([
                    'user_id' => $u,
                    'post_id' => $orm->lastId(),
                ])->into('postuser')->run();
            }

            for ($i = 0; $i < 10; $i++) {
                $row = [
                    'name' => $faker->name
                ];

                $orm->insert($row)->into('user')->run();
            }
        }
    }
