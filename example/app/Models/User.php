<?php
namespace App\Models;

use Octo\Elegant;

class User extends Elegant
{
    protected $table = 'user';

    public function setPasswordAttribute($input)
    {
        if ($input) {
            $this->attributes['password'] = hasher()->needsRehash($input) ? hasher()->make($input) : $input;
        }
    }
}
