<?php
namespace App\Services;

use App\Models\Settings;
use App\Models\User;
use Octo\FastRequest;
use function Octo\getCore;
use function Octo\gi;
use function Octo\isAke;
use function Octo\setCore;
use Octo\Ultimate;

class Auth
{
    /** @var array */
    protected static $instances = [];

    /** @var string */
    protected $namespace = 'core';

    /** @var string */
    protected $userKey = 'user';

    /** @var string */
    protected $userModel = User::class;

    /** @var string */
    protected const ADMIN_ROLE = 'admin';
    
    /** @var string */
    protected const USER_ROLE = 'user';

    /** @var array */
    protected $middlewares = ['before' => [], 'after' => []];

    /** @var null|callable */
    protected $resolveUser = null;

    /**
     * @param string $namespace
     * @return Auth
     */
    public static function getInstance(
        string $namespace = 'core',
        string $userKey = 'user',
        string $userModel = User::class
    ): self {
        if (!$instance = \Octo\isAke(static::$instances, $namespace, null)) {
            $instance = (new static)
                ->setNamespace($namespace)
                ->setUserKey($userKey)
                ->setUserModel($userModel)
            ;

            static::$instances[$namespace] = $instance;
        }

        return $instance;
    }

    /**
     * @return Ultimate
     */
    public function session(): Ultimate
    {
        return ultimate($this->namespace, $this->userKey, $this->userModel);
    }

    /**
     * @param null|string $key
     * @param null $default
     * @return mixed|Model|null
     */
    public function user(?string $key = null, $default = null)
    {
        if (is_callable($this->resolveUser)) {
            $user = cf($this->resolveUser);

            if (!empty($user)) {
                return null !== $key ? isAke($user, $key, $default) : $this->session()->makeUser($user['id']);
            }

            return $default;
        }

        return $this->session()->user($key, $default);
    }

    /**
     * @param $user
     * @return Auth
     */
    public function forUser($user): self
    {
        $this->resolveUser = function () use ($user) {
            return $user;
        };

        return $this;
    }

    /**
     * @return Auth
     */
    public function reset(): self
    {
        $this->resolveUser = null;

        return $this;
    }

    /**
     * @return bool
     */
    public function guest(): bool
    {
        if (null === ($user = $this->user())) {
            return true;
        }

        return null === $this->user();
    }

    /**
     * @return bool
     */
    public function logged(): bool
    {
        if (null === ($user = $this->user())) {
            return false;
        }

        return null !== $this->user('id');
    }

    /**
     * @param string $role
     * @return bool
     */
    public function is(string $role): bool
    {
        if (null === ($user = $this->user())) {
            return false;
        }

        return in_array($role, $this->user('roles', []));
    }

    /**
     * @param string $role
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        if (null === ($user = $this->user())) {
            return false;
        }

        return in_array($role, $this->user('roles', []));
    }

    /**
     * @return bool
     */
    public function isAdmin(): bool
    {
        if (null === ($user = $this->user())) {
            return false;
        }

        return $this->is(static::ADMIN_ROLE);
    }

    /**
     * @param mixed ...$args
     * @return bool
     */
    public function can(...$args): bool
    {
        if (null === ($user = $this->user())) {
            return false;
        }

        $permission = array_shift($args) . '.' . $this->namespace;
        $parameters = array_merge([$permission], $args);

        return $this->allows(...$parameters);
    }

    /**
     * @param mixed ...$args
     * @return bool
     */
    public function cannot(...$args): bool
    {
        if (null === ($user = $this->user())) {
            return true;
        }

        $permission = array_shift($args) . '.' . $this->namespace;
        $parameters = array_merge([$permission], $args);

        return $this->denies(...$parameters);
    }

    /**
     * @param mixed ...$args
     * @return bool
     */
    public function check(...$args): bool
    {
        if (null === ($user = $this->user())) {
            return false;
        }

        $permission = array_shift($args) . '.' . $this->namespace;
        $parameters = array_merge([$permission], $args);

        return $this->allows(...$parameters);
    }

    /**
     * @param string $name
     * @param $callback
     * @return Auth
     */
    public function policy(string $name, $callback): self
    {
        $rules = getCore('all.rules', []);

        $rules[$name . '.' . $this->namespace] = $callback;

        setCore('all.rules', $rules);

        return $this;
    }

    /**
     * @param mixed ...$args
     * @return bool
     */
    public function allows(...$args): bool
    {
        if (null === ($user = $this->user())) {
            return false;
        }

        $name = array_shift($args);

        $result = $this->callBefore($user, $name, $args);

        if (is_null($result)) {
            $result = false;

            $rule = isAke(getCore('all.rules', []), $name, null);

            if (is_callable($rule)) {
                $params = array_merge([$rule, $this->user()], $args);

                $result = cf(...$params);
            }
        }

        $this->callAfter($user, $name, $args, $result);

        return $result;
    }

    /**
     * @param mixed ...$args
     * @return bool
     */
    public function denies(...$args): bool
    {
        return !$this->allows(...$args);
    }

    /**
     * @return Settings
     */
    public function settings(): Settings
    {
        return gi()
            ->make(Settings::class, ['user.' . $this->user('id')], false)
            ->setStore(store('user'))
        ;
    }

    /**
     * @param mixed $namespace
     * @return Auth
     */
    public function setNamespace($namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * @param string $userKey
     * @return Auth
     */
    public function setUserKey(string $userKey): self
    {
        $this->userKey = $userKey;

        return $this;
    }

    /**
     * @param string $userModel
     * @return Auth
     */
    public function setUserModel(string $userModel): self
    {
        $this->userModel = $userModel;

        return $this;
    }

    /**
     * @param  callable  $callback
     * @return $this
     */
    public function before(callable $callback)
    {
        $this->middlewares['before'][] = $callback;

        return $this;
    }

    /**
     * @param  callable  $callback
     * @return $this
     */
    public function after(callable $callback)
    {
        $this->middlewares['after'][] = $callback;

        return $this;
    }

    /**
     * @param  $user
     * @param  string  $ability
     * @param  array  $arguments
     * @return bool|null
     */
    protected function callBefore($user, $ability, array $arguments)
    {
        $arguments = array_merge([$user, $ability], [$arguments]);

        foreach ($this->middlewares['before'] as $before) {
            if (!is_null($result = cf($before, ...$arguments))) {
                return $result;
            }
        }
    }

    /**
     * @param  $user
     * @param  string  $ability
     * @param  array  $arguments
     * @param  bool  $result
     * @return void
     */
    protected function callAfter($user, $ability, array $arguments, $result)
    {
        $arguments = array_merge([$user, $ability, $result], [$arguments]);

        foreach ($this->middlewares['after'] as $after) {
            cf($after, ...$arguments);
        }
    }

    /**
     * @param string $username
     * @return mixed
     */
    public function credentials(string $username = 'username')
    {
        return (new FastRequest)->only($username, 'password');
    }

    /**
     * @return array
     */
    public function roles(): array
    {
        return $this->user()->roles;
    }

    /**
     * @param string[] $roles
     * @return Model
     */
    public function attachRoles(array $roles)
    {
        $userRoles = $this->roles();

        return $this->user()->setRoles(unique($userRoles, $roles))->save();
    }

    /**
     * @param string $role
     * @return Model
     */
    public function attachRole(string $role)
    {
        return $this->attachRoles([$role]);
    }

    /**
     * @param string[] $roles
     * @return Model
     */
    public function detachRoles(array $roles)
    {
        $userRoles = $this->roles();

        $newRoles = [];

        foreach ($userRoles as $role) {
            if (!in_array($role, $roles)) {
                $newRoles[] = $role;
            }
        }

        return $this->user()->setRoles($newRoles)->save();
    }

    /**
     * @param string $role
     * @return Model
     */
    public function detachRole(string $role)
    {
        return $this->detachRoles([$role]);
    }

    /**
     * @param string[] $roles
     * @return \Illuminate\Database\Query\Builder
     */
    public function withRoles(array $roles)
    {
        $role = array_shift($roles);
        $query = $this->user()->newQuery()->as('roles', ';s:' . mb_strlen($role) . ':"' . $role . '";');

        foreach ($roles as $role) {
            $query = $query->orAs('roles', ';s:' . mb_strlen($role) . ':"' . $role . '";');
        }

        return $query;
    }

    /**
     * @param string $role
     * @return \Illuminate\Database\Query\Builder
     */
    public function withRole(string $role)
    {
        return $this->withRoles([$role]);
    }
}
