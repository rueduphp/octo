<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class CreatePost15310410019433
{
    public function up(Builder $schema)
    {
        $schema->create('post', function (Blueprint $table) {
            $table->collation = 'utf8_general_ci';
            $table->charset = 'utf8';
            $table->increments('id');
            $table->string('title');
            $table->longText('content');
            $table->unsignedInteger('category_id')->nullable()->index();
            $table->unsignedInteger('user_id')->index();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent();
            $table->engine = 'InnoDB';
        });
    }

    public function down(Builder $schema)
    {
        $schema->dropIfExists('post');
    }
}
