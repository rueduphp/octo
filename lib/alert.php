<?php

namespace Octo;

class Alert extends Elegant
{
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'notification';

    /**
     * The guarded attributes on the model.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'data'      => 'array',
        'read_at'   => 'datetime',
    ];

    /**
     * Get the notifiable entity that the notification belongs to.
     */
    public function notifiable()
    {
        return $this->morphTo();
    }

    /**
     * Mark the notification as read.
     *
     * @return void
     */
    public function markAsRead(): void
    {
        if (is_null($this->read_at)) {
            $this->forceFill(['read_at' => $this->freshTimestamp()])->save();
        }
    }

    /**
     * Mark the notification as unread.
     *
     * @return void
     */
    public function markAsUnread(): void
    {
        if (! is_null($this->read_at)) {
            $this->forceFill(['read_at' => null])->save();
        }
    }

    /**
     * Determine if a notification has been read.
     *
     * @return bool
     */
    public function read(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Determine if a notification has not been read.
     *
     * @return bool
     */
    public function unread(): bool
    {
        return $this->read_at === null;
    }

    /**
     * Create a new database notification collection instance.
     *
     * @param  array  $models
     * @return Dyn
     */
    public function newCollection(array $models = [])
    {
        $collection = dyn(coll($models));

        $collection->macro('markAsRead', function (Alert $notification) {
            $notification->markAsRead();
        });

        $collection->macro('markAsUnread', function (Alert $notification) {
            $notification->markAsUnread();
        });

        return $collection;
    }

    /**
     * @param $from
     * @param $notifiable
     * @param $data
     *
     * @return Alert
     */
    public static function sendToDatabase($from, $notifiable, $data)
    {
        return static::create([
            'id'                => uuid(),
            'notifiable_type'   => get_class($notifiable),
            'notifiable_id'     => $notifiable->getKey(),
            'type'              => get_class($from),
            'data'              => serialize($data),
            'read_at'           => null,
        ]);
    }

    /**
     * @param $from
     * @param $notifiable
     * @param $data
     *
     * @return bool
     */
    public static function sendToRedis($from, $notifiable, $data)
    {
        $key = 'notifications.' . uuid();

        $record = [
            'notifiable_type'   => get_class($notifiable),
            'notifiable_id'     => $notifiable->getKey(),
            'type'              => get_class($from),
            'data'              => $data,
            'read_at'           => null,
            'created_at'        => time(),
        ];

        try {
            redis()->set($key, serialize($record));

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    public static function getFromRedis(string $key)
    {
        $row = redis()->get($key);

        return $row ? unserialize($row) : null;
    }
}
