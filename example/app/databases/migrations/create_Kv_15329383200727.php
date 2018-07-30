<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

class CreateKv15329383200727
{
    public function up(Builder $schema)
    {
        $schema->create('kv', function (Blueprint $table) {
            $table->collation = 'utf8_general_ci';
            $table->charset = 'utf8';
            $table->string('k')->primary()->unique();
            $table->longText('v')->nullable();
            $table->unsignedBigInteger('e')->index()->default(0);
            $table->engine = 'InnoDB';
        });
    }

    public function down(Builder $schema)
    {
        $schema->dropIfExists('kv');
    }
}
