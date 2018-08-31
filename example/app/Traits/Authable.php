<?php
namespace App\Traits;

use App\Models\Settings;
use App\Models\User;
use function Octo\gi;

trait Authable
{
    /**
     * @param string $name
     * @param $callback
     * @return $this
     */
    public function addPermission(string $name, $callback)
    {
        trust()->policy($name, $callback);

        return $this;
    }

    public function allows(string $name)
    {
        return $this->addPermission($name, function () {
            return true;
        });
    }

    /**
     * @param string $name
     * @return $this
     */
    public function denies(string $name)
    {
        return $this->addPermission($name, function () {
            return false;
        });
    }

    /**
     * @param mixed ...$args
     * @return bool
     */
    public function isGranted(...$args)
    {
        return $this->can(...$args);
    }

    /**
     * @param mixed ...$args
     * @throws \Exception
     */
    public function untilIsGranted(...$args)
    {
        $status = $this->can(...$args);

        if (false === $status) {
            throw new \Exception('This action is not granted.');
        }
    }

    /**
     * @param mixed ...$args
     * @return bool
     */
    public function can(...$args): bool
    {
        return trust()->can(...$args);
    }

    /**
     * @param mixed ...$args
     * @return bool
     */
    public function cannot(...$args): bool
    {
        return trust()->cannot(...$args);
    }

    /**
     * @param string $role
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles());
    }

    /**
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * @return array
     */
    public function roles(): array
    {
        return user()->roles;
    }

    /**
     * @param string[] $roles
     * @return User
     */
    public function attachRoles(array $roles)
    {
        $userRoles = $this->roles();

        return user()->setRoles(unique($userRoles, $roles))->save();
    }

    /**
     * @param string $role
     * @return User
     */
    public function attachRole(string $role)
    {
        return $this->attachRoles([$role]);
    }

    /**
     * @param string[] $roles
     * @return User
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

        return user()->setRoles($newRoles)->save();
    }

    /**
     * @param string $role
     * @return User
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
        $query = $this->newQuery()->as('roles', ';s:' . mb_strlen($role) . ':"' . $role . '";');

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

    /**
     * @return Settings
     * @throws \ReflectionException
     */
    public function settings()
    {
        return gi()->make(Settings::class, ['user.' . $this->id], false)->setStore(store('user'));
    }
}
