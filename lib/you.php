<?php
namespace Octo;

class You
{
    use Eventable;

    /** @var string  */
    protected $userKey = 'user';

    /** @var string  */
    protected $namespace = 'auth';

    /** @var array  */
    protected static $providers = [];

    /** @var array  */
    protected static $roles = [];

    /** @var You */
    protected static $instance;

    /**
     * @throws \ReflectionException
     */
    public function __construct()
    {
        sessionKey('you.' . $this->getNamespace());
        static::setRole('guest');
        static::setRole('admin');
    }

    /**
     * @return string
     */
    public function getUserKey(): string
    {
        return $this->userKey;
    }

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return You
     *
     * @throws \ReflectionException
     */
    public static function called()
    {
        if (is_null(static::$instance)) {
            static::$instance = gi()->singleton(get_called_class());
        }

        return static::$instance;
    }

    /**
     * @return array
     */
    public function getProviders(): array
    {
        return isAke(static::$providers, get_called_class(), []);
    }

    /**
     * @return array
     */
    public function getRoles(): array
    {
        return isAke(static::$roles, get_called_class(), []);
    }

    /**
     * @param string $name
     * @return mixed
     * @throws \ReflectionException
     */
    public static function getProvider(string $name)
    {
        return isAke(static::called()->getProviders(), $name, null);
    }

    /**
     * @param string $name
     * @return mixed
     * @throws \ReflectionException
     */
    public static function getRole(string $name)
    {
        return isAke(static::called()->getRoles(), $name, null);
    }

    /**
     * @return Live|Session
     * @throws \TypeError
     */
    public static function getSession()
    {
        return getSession();
    }

    /**
     * @return string
     * @throws \ReflectionException
     */
    public static function init()
    {
        return static::token();
    }

    /**
     * @return string
     * @throws \ReflectionException
     */
    public static function token(): string
    {
        return sessionKey('you.' . static::called()->getNamespace());
    }

    /**
     * @param string $type
     * @param callable $callable
     */
    public static function setProvider(string $type, callable $callable)
    {
        static::$providers[get_called_class()][$type] = $callable;
    }

    /**
     * @param string $name
     * @param $value
     */
    public static function setRole(string $name, $value = null)
    {
        $value = is_null($value) ? $name : $value;
        static::$roles[get_called_class()][$name] = $value;
    }

    /**
     * @param null|string $key
     *
     * @return array|mixed|null
     *
     * @throws \ReflectionException
     * @throws \TypeError
     */
    public static function are(?string $key = null)
    {
        $user = static::user();

        if (!is_null($user)) {
            if (!is_null($key)) {
                return dataget($user, $key, null);
            }
        }

        return $user;
    }

    /**
     * @param array ...$args
     *
     * @return mixed
     *
     * @throws \ReflectionException
     * @throws \TypeError
     */
    public static function user(...$args)
    {
        $user = static::getProvider('user');

        if (is_callable($user)) {
            return $user(...$args);
        }

        return static::called()->getSession()[static::called()->getUserKey()];
    }

    /**
     * @param string $key
     *
     * @return array|mixed|null
     *
     * @throws \ReflectionException
     * @throws \TypeError
     */
    public static function have(string $key)
    {
        return static::are($key);
    }

    /**
     * @param string $type
     * @param array $args
     * @return mixed|null
     * @throws \ReflectionException
     */
    public static function call(string $type, array $args = [])
    {
        $providers = static::called()->getProviders();

        $provider = isAke($providers, $type, null);

        if (is_callable($provider)) {
            return $provider(...$args);
        }

        return null;
    }

    /**
     * @param string $m
     * @param array $a
     * @return mixed|null
     * @throws \ReflectionException
     * @throws \TypeError
     */
    public static function __callStatic(string $m, array $a)
    {
        $called = static::called();

        $params = array_merge([$m], $a);

        $callable = isAke($called->getProviders(), $m, null);

        if (is_callable($callable)) {
            return $called->call(...$params);
        }

        $params = array_merge([$called->getSession(), $m], $a);

        return instanciator()->call(...$params);
    }

    /**
     * @param $user
     *
     * @return $this
     *
     * @throws \ReflectionException
     */
    public static function become($user)
    {
        $callback = function ($key = null) use ($user) {
            if (!is_string($key)) {
                $key = null;
            }

            if (!is_null($user)) {
                if (!is_null($key)) {
                    return dataget($user, $key, null);
                }
            }

            return $user;
        };

        $called = static::called();

        $called->setProvider('user', $callback);

        return $called;
    }

    /**
     * @param $user
     *
     * @return You
     *
     * @throws \ReflectionException
     * @throws \TypeError
     */
    public static function as($user): You
    {
        $callback = function ($key = null) use ($user) {
            if (!is_string($key)) {
                $key = null;
            }

            if (!is_null($user)) {
                if (!is_null($key)) {
                    return dataget($user, $key, null);
                }
            }

            return $user;
        };

        $oldUser = static::user();

        set('you.old.user', $oldUser);

        /** @var You $new */
        $new = instanciator()->factory(get_called_class());

        $new->setProvider('user', $callback);

        return $new;
    }

    /**
     * @return You
     * @throws \ReflectionException
     * @throws \TypeError
     */
    public static function recover(): You
    {
        $oldUser = get('you.old.user', null);

        return static::as($oldUser);
    }
    /**
     * @return array
     *
     * @throws \ReflectionException
     */
    public static function rules(): array
    {
        return get(get_called_class() . '.rules.' . static::called()->getNamespace(), []);
    }

    /**
     * @param string $key
     * @param callable $callable
     * @param bool $paste
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function rule(string $key, callable $callable, bool $paste = false)
    {
        $policies = static::rules();

        if (false === $paste) {
            $policy = aget($policies, $key, false);

            if (is_callable($policy)) {
                throw new Exception("The rule {$key} ever exists.");
            }
        }

        $policies[$key] = $callable;

        set(get_called_class() . '.rules.' . static::called()->getNamespace(), $policies);
    }

    /**
     * @param $permissions
     * @return bool
     * @throws \ReflectionException
     * @throws \TypeError
     */
    public static function canAtLeast($permissions)
    {
        if (true === static::areAuth()) {
            $permissions = (array) $permissions;
            $policies = static::rules();

            foreach ($permissions as $permission) {
                $policy = isAke($policies, $permission, false);

                if (is_callable($policy) && true === $policy(static::user())) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array ...$args
     *
     * @return bool
     *
     * @throws \ReflectionException
     * @throws \TypeError
     */
    public static function can(...$args): bool
    {
        if (true === static::areAuth()) {
            $key        = array_shift($args);
            $policies   = get(get_called_class() . '.policies.' . static::called()->getNamespace(), []);
            $policy     = isAke($policies, $key, false);

            if (is_callable($policy)) {
                $params = array_merge([static::user()], $args);

                if ($policy instanceof \Closure) {
                    return $policy(...$params);
                } else {
                    if (is_array($policy)) {
                        $params = array_merge($policy, $params);

                        return instanciator()->call(...$params);
                    } else {
                        $args = array_merge([$policy], $params);

                        return callCallable(...$args);
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param array ...$args
     * @return bool
     * @throws \ReflectionException
     * @throws \TypeError
     */
    public static function cannot(...$args): bool
    {
        return !static::can(...$args);
    }

    /**
     * @param array ...$args
     * @return bool
     * @throws \ReflectionException
     * @throws \TypeError
     */
    public static function cant(...$args): bool
    {
        return static::cannot(...$args);
    }

    /**
     * @param string $key
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function allows(string $key)
    {
        static::rule($key, function () {
            return true;
        }, true);
    }

    /**
     * @param string $key
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function denies(string $key)
    {
        static::rule($key, function () {
            return false;
        }, true);
    }

    /**
     * @param string $key
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function authorize(string $key)
    {
        static::rule($key, function () {
            return true;
        }, true);
    }

    /**
     * @param string $key
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function forbid(string $key)
    {
        static::rule($key, function () {
            return false;
        }, true);
    }

    /**
     * @return mixed
     * @throws \ReflectionException
     * @throws \TypeError
     */
    public function __invoke()
    {
        return self::called()->user();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getToken();
    }

    /**
     * @return bool
     * @throws \ReflectionException
     * @throws \TypeError
     */
    public static function areAuth(): bool
    {
        return null !== static::user();
    }

    /**
     * @return bool
     * @throws \ReflectionException
     * @throws \TypeError
     */
    public static function areGuest(): bool
    {
        return null === static::user();
    }
}