<?php
namespace App\Repositories;

use App\Models\User as UserModel;
use Octo\Bcrypt;
use Octo\Elegant;
use Octo\FastRequest;
use Octo\FastSessionInterface;
use Octo\Ultimate;

class User
{
    /**
     * @var FastRequest
     */
    private $request;

    /**
     * @var Ultimate
     */
    private $session;

    /**
     * @var Elegant
     */
    private $model;

    /**
     * @var Bcrypt
     */
    private $hash;

    public function __construct(FastRequest $request, FastSessionInterface $session, Bcrypt $hash)
    {
        $this->request  = $request;
        $this->session  = $session;
        $this->hash     = $hash;
        $this->model    = model($session->getUserModel());
    }

    /**
     * @param string $email
     * @param string $password
     * @param null|string $remember
     * @return null
     * @throws \ReflectionException
     */
    public function login(string $email, string $password, ?string $remember = null)
    {
        /** @var null|UserModel $user */
        $user = $this->model->whereEmail($email)->first();

        if (null !== $user) {
            if ($this->hash->check($password, $user->password)) {
                $user->logged_at = now();

                if ('on' === $remember) {
                    $user->remember_token = \Octo\forever();
                }

                $user->save();

                $this->session->set($this->session->getUserKey(), $user->toArray());

                return $user;
            }
        }

        return null;
    }

    public function connect(UserModel $user)
    {
        $this->session->set($this->session->getUserKey(), $user->toArray());
    }

    /**
     * @throws \ReflectionException
     */
    public function logout()
    {
        $user = $this->model->find(user('id'));

        if ($user) {
            $user->remember_token = null;
            $user->save();
        }

        $this->session->forget($this->session->getUserKey());
    }
}
