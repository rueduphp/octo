<?php
use Illuminate\Support\Facades\Artisan;

Artisan::command('foo', function () {
    $this->comment('bar');
})->describe('Closure command example');
