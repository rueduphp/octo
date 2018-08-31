<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class CreateLogs15330245614794
{
    public function up(Builder $schema)
    {
        $schema->create('logs', function (Blueprint $table) {
            $table->collation = 'utf8_general_ci';
            $table->charset = 'utf8';
            $table->increments('id');
            $table->text('type');
            $table->text('model');
            $table->longText('message');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->engine = 'InnoDB';
        });
    }

    public function down(Builder $schema)
    {
        $schema->dropIfExists('logs');
    }
}
