<?php
    use Octo\Strings;
    use Phinx\Seed\AbstractSeed;
    use Tests\Post;
    use Tests\User;
    use function Octo\faker;


    class PostSeeds extends AbstractSeed
    {
        public function run()
        {
            $faker = faker();

            $orm = new Orm();

            $morph_type = User::class;

            for ($i = 0; $i < 10; $i++) {
                $row = [
                    'content'   => $t = $faker->sentence,
                    'user_id'   => $u = rand(1, 10),
                    'title'     => Strings::urlize($t)
                ];

                $orm->insert($row)->into('post')->run();

                $post_id = $orm->lastId();

                $orm->insert([
                    'user_id' => $u,
                    'post_id' => $post_id,
                ])->into('postuser')->run();

                $commentable_id = $morph_type == User::class ? $u : $post_id;
                $commentable_type =  User::class == $morph_type ? UserModel::class : PostModel::class;

                $orm->insert([
                    'commentable_id'    => $commentable_id,
                    'commentable_type'  => $commentable_type,
                    'morph_id'          => $commentable_id,
                    'morph_type'        => $morph_type,
                    'body'              => $faker->sentence
                ])->into('comment')->run();

                $morph_type = User::class == $morph_type ? Post::class : User::class;
            }

            for ($i = 0; $i < 10; $i++) {
                $row = [
                    'name' => $faker->name
                ];

                $orm->insert($row)->into('user')->run();
            }
        }
    }
