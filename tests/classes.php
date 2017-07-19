<?php
    namespace Tests;

    use Octo\Entity;

    class Post extends Entity
    {
        public function user()
        {
            return User::class;
        }

        public function onDeleting($model)
        {
            return true;
        }

        public function scopeTest($query)
        {
            return $query->where('id', '>', 5);
        }
    }

    class User extends Entity
    {
        public function posts()
        {
            return Post::class;
        }
    }

    class Postuser extends Entity
    {
        public function user()
        {
            return User::class;
        }

        public function post()
        {
            return Post::class;
        }
    }
