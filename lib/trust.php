<?php
namespace Octo;

use Closure;
use Exception;

class Trust
{
    use Eventable;

    /**
     * @var string
     */
    protected $userKey = 'user';

    /**
     * @var string
     */
    protected $namespace = 'auth';

    /**
     * @var string
     */
    protected $driver = Caching::class;

    protected $providers = [
        'login'     => null,
        'logout'    => null,
        'session'   => null,
        'user'      => null,
    ];

    public function __construct()
    {
        trust($this);
        getContainer()->define('trust', $this);
    }

    /**
     * @return bool
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function init(): bool
    {
        return static::isAuth();
    }

    /**
     * @return Trust
     *
     * @throws \ReflectionException
     */
    protected static function called(): Trust
    {
        return instanciator()->singleton(get_called_class());
    }

    /**
     * @return string
     *
     * @throws \ReflectionException
     */
    public static function getToken(): string
    {
        return sessionKey(static::called()->getNamespace());
    }

    /**
     * @return bool
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function isAuth():bool
    {
        return null !== static::user();
    }

    /**
     * @return bool
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function check():bool
    {
        return null !== static::user();
    }

    /**
     * @return bool
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function isGuest():bool
    {
        return null === static::user();
    }

    /**
     * @return bool
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function guest():bool
    {
        return null === static::user();
    }

    /**
     * @param null|string $key
     *
     * @return Trust
     *
     * @throws \ReflectionException
     */
    public function setToken(?string $key = null): self
    {
        if (false === isCli()) {
            $name = SITE_NAME . '_' . static::called()->getNamespace();

            if (is_null($key)) {
                $key = sha1(uniqid(sha1(uniqid(null, true)), true));
            }

            setcookie($name, $key, strtotime('+1 hour'), '/');
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return string
     */
    public function getDriver(): string
    {
        return $this->driver;
    }

    /**
     * @param string $m
     * @param array $a
     *
     * @return mixed|null
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function __callStatic(string $m, array $a)
    {
        $called = static::called();

        $params = array_merge([$m], $a);

        $callable = isAke($called->providers, $m, null);

        if (is_callable($callable)) {
            return $called->callProvider(...$params);
        }

        $magicMethod = 'getDefault' . ucfirst(Inflector::camelize($m));

        if (in_array($magicMethod, get_class_methods($called))) {
            return $called->callProvider(...$params);
        }

        $params = array_merge([static::session(), $m], $a);

        return instanciator()->call(...$params);
    }

    /**
     * @return string
     */
    public function getUserKey(): string
    {
        return $this->userKey;
    }

    /**
     * @param string $type
     *
     * @param callable|null $callable
     *
     * @return Trust
     */
    public function setProvider(string $type, ?callable $callable = null): self
    {
        $this->providers[$type] = $callable;

        return $this;
    }

    /**
     * @return Closure
     */
    protected function getDefaultSession(): Closure
    {
        return function () {
            $driver = instanciator()->singleton(
                $this->getDriver(),
                sessionKey($this->getNamespace())
            );

            return new Live($driver);
        };
    }

    /**
     * @return Closure
     */
    protected function getDefaultUser(): Closure
    {
        return function ($key = null) {
            if (!is_string($key)) {
                $key = null;
            }

            $user = $this->session()[$this->getUserKey()];

            if (!is_null($user)) {
                if (!is_null($key)) {
                    return dataget($user, $key, null);
                }
            }

            return $user;
        };
    }

    /**
     * @return mixed|null
     * 
     * @throws \ReflectionException
     */
    public function callProvider(...$args)
    {
        $type = array_shift($args);

        $callable = isAke($this->providers, $type, null);

        if (is_null($callable)) {
            $magicMethod = 'getDefault' . ucfirst(Inflector::camelize($type));
            $callable = $this->{$magicMethod}();
        }

        if (is_callable($callable)) {
            $params = !is_array($callable)
                ? array_merge([$callable], array_merge($args, [$this]))
                : array_merge($callable, array_merge($args, [$this]))
            ;

            return callCallable(...$params);
        }

        return null;
    }

    /**
     * @return mixed|null
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function login()
    {
        return static::connexion('login');
    }

    /**
     * @return mixed|null
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function logout()
    {
        return static::connexion('logout');
    }

    /**
     * @param string $type
     * @return mixed|null
     * @throws Exception
     * @throws \ReflectionException
     */
    protected static function connexion(...$args)
    {
        $type = array_shift($args);
        $params = array_merge([$type], $args);

        $called = static::called();

        $status = $called->callProvider(...$params);

        $called->fire('trust.' . $type . '.' . $called->getNamespace(), $status, static::session());

        $user = static::user();

        getContainer()->setUser($user);

        return $status;
    }

    /**
     * @param array ...$args
     * @return mixed|null
     *
     * @throws \ReflectionException
     * @throws \TypeError
     */
    public static function session(...$args)
    {
        $params = array_merge(['session'], $args);

        $session = static::called()->callProvider(...$params);

        getContainer()->setSession($session);

        return $session;
    }

    /**
     * @param array ...$args
     * @return mixed|null
     * @throws \ReflectionException
     */
    public static function user(...$args)
    {
        $params = array_merge(['user'], $args);

        return static::called()->callProvider(...$params);
    }

    /**
     * @param $user
     *
     * @return Trust
     *
     * @throws \ReflectionException
     */
    public static function force($user)
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
     * @return Trust
     *
     * @throws \ReflectionException
     */
    public static function forUser($user): Trust
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

        Registry::set('trust.old.user', $oldUser);

        /** @var Trust $new */
        $new = instanciator()->factory(get_called_class());

        $new->setProvider('user', $callback);

        return $new;
    }

    /**
     * @return Trust
     *
     * @throws \ReflectionException
     */
    public static function recoverUser(): Trust
    {
        $oldUser = Registry::get('trust.old.user', null);

        return static::forUser($oldUser);
    }

    /**
     * @return array
     *
     * @throws \ReflectionException
     */
    public static function policies(): array
    {
        return Registry::get('trust.policies.' . static::called()->getNamespace(), []);
    }

    /**
     * @param string $key
     * @param callable $callable
     * @param bool $paste
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function policy(string $key, callable $callable, bool $paste = false)
    {
        $policies = static::policies();

        if (false === $paste) {
            $policy = aget($policies, $key, false);

            if (is_callable($policy)) {
                throw new Exception("The rule {$key} ever exists.");
            }
        }

        $policies[$key] = $callable;

        Registry::set('trust.policies.' . static::called()->getNamespace(), $policies);
    }

    /**
     * @param $permissions
     *
     * @return bool
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function canAtLeast($permissions)
    {
        if (true === static::isAuth()) {
            $permissions = (array) $permissions;
            $policies = static::policies();

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
     * @return bool
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function can(...$args): bool
    {
        if (true === static::isAuth()) {
            $key        = array_shift($args);
            $policies   = Registry::get('trust.policies.' . static::called()->getNamespace(), []);
            $policy     = isAke($policies, $key, false);

            if (is_callable($policy)) {
                $params = array_merge([static::user()], $args);

                if ($policy instanceof Closure) {
                    $params = array_merge([$policy], $params);

                    return instanciator()->makeClosure(...$params);
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
     *
     * @return bool
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function cannot(...$args): bool
    {
        return !static::can(...$args);
    }

    /**
     * @param array ...$args
     *
     * @return bool
     *
     * @throws Exception
     * @throws \ReflectionException
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
        static::policy($key, function () {
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
        static::policy($key, function () {
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
        static::policy($key, function () {
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
        static::policy($key, function () {
            return false;
        }, true);
    }
}