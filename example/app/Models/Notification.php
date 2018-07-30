<?php

namespace App\Models;

use App\Services\Model;

class Notification extends Model
{
    protected $table = 'notification';
    protected $forceCache = true;
}
