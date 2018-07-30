<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\Model;

class Category extends Model
{
    protected $table = 'category';
    protected $indexables = ['name'];
    protected $forceCache = false;
    protected $rules = [];

    /**
     * @return HasMany
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
