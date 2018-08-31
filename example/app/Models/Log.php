<?php

namespace App\Models;

use App\Services\Model;

class Log extends Model
{
    protected $table = 'logs';
    protected $indexables = [];
    protected $forceCache = false;
    protected $rules = [];
    public $timestamps = false;
    protected $dates = ['created_at'];

    /**
     * @param string $model
     * @param string $message
     * @return mixed
     */
    public static function success(string $model, string $message)
    {
        return static::write('success', $model, $message);
    }

    /**
     * @param string $model
     * @param string $message
     * @return mixed
     */
    public static function error(string $model, string $message)
    {
        return static::write('error', $model, $message);
    }

    /**
     * @param string $model
     * @param string $message
     * @return mixed
     */
    public static function warning(string $model, string $message)
    {
        return static::write('warning', $model, $message);
    }

    /**
     * @param string $type
     * @param string $model
     * @param string $message
     * @return mixed
     */
    public static function write(string $type, string $model, string $message)
    {
        return static::create(compact('type', 'model', 'message'));
    }
}
