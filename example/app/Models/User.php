<?php
namespace App\Models;

use App\Services\Model;
use App\Traits\Attributable;
use App\Traits\Authable;
use Octo\Inflector;
use Octo\Notifiable;

class User extends Model
{
    use Authable;
    use Notifiable;
    use Attributable;

    /**
     * @var string
     */
    protected $table = 'user';
    protected $indexables = ['email'];
    protected $forceCache = false;
    protected $rules = [];

    /**
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'logged_at'
    ];

    public function setPasswordAttribute($input)
    {
        if ($input) {
            $this->attributes['password'] = hasher()->needsRehash($input) ? hasher()->make($input) : $input;
        }
    }

    public function setRolesAttribute($input)
    {
        $input = $input ?? [];

        $this->attributes['roles'] = serialize($input);
    }

    /**
     * @param $value
     * @return mixed
     */
    public function getRolesAttribute($value)
    {
        $value = $value ?? serialize([]);

        try {
            return unserialize($value);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        unset($array['password']);

        $array['photo'] = 'https://www.gravatar.com/avatar/'.md5(Inflector::lower($array['email'])).'.jpg?s=200&d=mm';

        return $array;
    }
}
