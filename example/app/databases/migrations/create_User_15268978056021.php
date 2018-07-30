<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class CreateUser15268978056021
{
    public function up(Builder $schema)
    {
        $schema->create('user', function (Blueprint $table) {
            $table->collation = 'utf8_general_ci';
            $table->charset = 'utf8';
            $table->increments('id');
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->unique()->index();
            $table->text('roles')->nullable()->default(serialize([]));
            $table->string('password');
            $table->string('remember_token')->nullable()->index();
            $table->timestamp('logged_at')->nullable();
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
