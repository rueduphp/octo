<?php

namespace App\Models;

use App\Services\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    protected $table = 'post';
    protected $indexables = ['title'];
    protected $forceCache = false;
    protected $rules = [];

    /**
     * @return BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
