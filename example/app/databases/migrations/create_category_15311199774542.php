<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class CreateCategory15311199774542
{
    public function up(Builder $schema)
    {
        $schema->create('category', function (Blueprint $table) {
            $table->collation = 'utf8_general_ci';
            $table->charset = 'utf8';
            $table->increments('id');
            $table->string('name');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent();
            $table->engine = 'InnoDB';
        });
    }

    public function down(Builder $schema)
    {
        $schema->dropIfExists('category');
    }
}
