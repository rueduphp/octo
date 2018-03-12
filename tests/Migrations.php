<?php
namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Octo\Orm;
use Octo\Strings;
use PostModel;
use UserModel;
use function Octo\faker;

class Migrations
{
    public static function migrate(Builder $schema)
    {
        $schema->create('post', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->integer('user_id')->default(1)->index();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent();
        });

        $schema->create('user', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent();
        });

        $schema->create('postuser', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->nullable()->index();
            $table->integer('post_id')->nullable()->index();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent();
        });

        $schema->create('comment', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('commentable_id')->nullable()->index();
            $table->integer('morph_id')->nullable()->index();
            $table->string('commentable_type')->nullable();
            $table->string('morph_type')->nullable();
            $table->text('body')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent();
        });

        $schema->create('caching', function (Blueprint $table) {
            $table->string('k')->primary()->unique();
            $table->longText('v')->nullable();
            $table->unsignedBigInteger('e')->index();
        });

        $schema->create('notification', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent();
        });
    }

    public static function seeds(Orm $orm)
    {
        $faker = faker();

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
