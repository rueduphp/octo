<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class CreateUser15268978056021
{
    public function up(Builder $schema)
    {
        $schema->create('user', function (Blueprint $table) {
            $table->charset = 'utf8';
            $table->collation = 'utf8_general_ci';
            $table->increments('id');
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('remember_token')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent();
            $table->engine = 'InnoDB';
        });
    }

    public function down(Builder $schema)
    {
        $schema->dropIfExists('user');
    }
}
