<?php
namespace App\Services;

use App\Models\Settings;
use App\Models\User;
use Closure;
use Octo\FastContainerException;
use function Octo\getCore;
use function Octo\gi;
use function Octo\isAke;
use function Octo\setCore;
use Octo\Ultimate;
use ReflectionException;

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
     * @return mixed|null
     * @throws FastContainerException
     * @throws ReflectionException
     */
    public function user(?string $key = null, $default = null)
    {
        return $this->session()->user($key, $default);
    }

    /**
     * @return bool
     */
    public function guest(): bool
    {
        return null === $this->user();
    }

    /**
     * @return bool
     * @throws FastContainerException
     * @throws ReflectionException
     */
    public function logged(): bool
    {
        return null !== $this->user('id');
    }

    /**
     * @param string $role
     * @return bool
     * @throws FastContainerException
     * @throws ReflectionException
     */
    public function is(string $role): bool
    {
        return in_array($role, $this->user('roles', []));
    }

    /**
     * @return bool
     * @throws FastContainerException
     * @throws ReflectionException
     */
    public function isAdmin(): bool
    {
        return $this->is(static::ADMIN_ROLE);
    }

    /**
     * @param mixed ...$args
     * @return bool
     */
    public function can(...$args): bool
    {
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
     * @throws FastContainerException
     * @throws ReflectionException
     */
    public function allows(...$args): bool
    {
        $name = array_shift($args);

        $rule = isAke(getCore('all.rules', []), $name, null);

        if (is_callable($rule)) {
            $params = array_merge([$rule, $this->user()], $args);

            if ($rule instanceof Closure) {
                return gi()->makeClosure(...$params);
            }

            return gi()->call(...$params);
        }

        return false;
    }

    /**
     * @param mixed ...$args
     * @return bool
     * @throws FastContainerException
     * @throws ReflectionException
     */
    public function denies(...$args): bool
    {
        return !$this->allows(...$args);
    }

    /**
     * @return Settings
     * @throws \ReflectionException
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
}
