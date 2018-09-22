<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use function Octo\aget;

class AuthUser implements UserProvider
{
    /**
     * @var array
     */
    protected $config = [];

    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * @param mixed $identifier
     * @return Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        return $this->createModel()->find($identifier);
    }

    /**
     * @param mixed $identifier
     * @param string $token
     * @return Authenticatable|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|null|object
     */
    public function retrieveByToken($identifier, $token)
    {
        return $this->createModel()
            ->newQuery()
            ->where('id', $identifier)
            ->where('remember_token', $token)
            ->first()
        ;
    }

    /**
     * @param Authenticatable $user
     * @param string $token
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        $user->setRememberToken($token);
        $user->save();
    }

    /**
     * @param array $credentials
     * @return Authenticatable|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|null|object
     */
    public function retrieveByCredentials(array $credentials)
    {
        $query = $this->createModel()->newQuery();

        if ($username = aget($credentials, 'username')) {
            return $query->whereUsername($username)->first();
        }

        if ($email = aget($credentials, 'email')) {
            return $query->whereEmail($email)->first();
        }

        return null;
    }

    /**
     * @param Authenticatable $user
     * @param array $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        if (!isset($credentials['password'])) {
            return false;
        }

        return (new Password)->check($credentials['password'], $user->password);
    }

    /**
     * @return User
     */
    protected function createModel()
    {
        $model = aget($this->config, 'model');

        return $model ? new $model : new User;
    }
}
