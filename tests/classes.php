<?php
    namespace Tests;

    use Octo\Entity;

    class Post extends Entity
    {
        protected $fillable = ['content', 'fake', 'title', 'user_id'];

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

        public function comments()
        {
            return $this->morphMany(Comment::class);
        }
    }

    class User extends Entity
    {
        public function posts()
        {
            return Post::class;
        }

        public function comments()
        {
            return $this->morphMany(Comment::class);
        }
    }

    class Comment extends Entity
    {
        public function commentable()
        {
            return $this->morphTo();
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

    class Job
    {
        public function process()
        {

        }
    }
