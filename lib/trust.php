<?php
namespace Octo;

use Closure;
use Exception;

class Trust
{
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
    ];

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
     * @param $user
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function force($user)
    {
        static::session()[static::called()->getUserKey()] = $user;
    }

    /**
     * @return bool
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function isAuth():bool
    {
        return null !== static::session()[static::called()->getUserKey()];
    }

    /**
     * @return bool
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function isGuest():bool
    {
        return null === static::session()[static::called()->getUserKey()];
    }

    /**
     * @param null|string $key
     *
     * @return array|mixed|null
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function user(?string $key = null)
    {
        $user = static::session()[static::called()->getUserKey()];

        if (!is_null($user)) {
            if (!is_null($key)) {
                return dataget($user, $key, null);
            }
        }

        return $user;
    }

    /**
     * @param null|string $key
     *
     * @throws \ReflectionException
     */
    public function setToken(?string $key = null)
    {
        if (false === isCli()) {
            $name = SITE_NAME . '_' . static::called()->getNamespace();

            if (is_null($key)) {
                $key = sha1(uniqid(sha1(uniqid(null, true)), true));
            }

            setcookie($name, $key, strtotime('+1 hour'), '/');
        }
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
     * @param callable $callable
     *
     * @return Trust
     */
    public function setProvider(string $type, callable $callable): self
    {
        $this->providers[$type] = $callable;

        return $this;
    }

    /**
     * @return Closure
     *
     * @throws \Octo\Exception
     * @throws \ReflectionException
     */
    protected function getDefaultSession()
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
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function callProvider()
    {
        $args = func_get_args();

        $type = array_shift($args);

        $callable = isAke($this->providers, $type, null);

        if (is_null($callable)) {
            $magicMethod = 'getDefault' . ucfirst(Inflector::camelize($type));
            $callable = $this->{$magicMethod}();
        }

        if (is_callable($callable)) {
            $params = array_merge([$callable], array_merge($args, [$this]));

            return callCallable(...$params);
        }

        return null;
    }

    /**
     * @return mixed|null
     * @throws \ReflectionException
     */
    public static function login()
    {
        $params = array_merge(['login'], func_get_args());

        $status = static::called()->callProvider(...$params);

        getEventManager()->fire('trust.login.' . static::called()->getNamespace(), $status, static::session());

        return $status;
    }

    /**
     * @return mixed|null
     * @throws \ReflectionException
     */
    public static function logout()
    {
        $params = array_merge(['logout'], func_get_args());

        $status = static::called()->callProvider(...$params);

        getEventManager()->fire('trust.logout.' . static::called()->getNamespace(), $status, static::session());

        return $status;
    }

    /**
     * @return mixed|null
     * @throws \ReflectionException
     */
    public static function session()
    {
        $params = array_merge(['session'], func_get_args());

        return static::called()->callProvider(...$params);
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
        $policies = Registry::get('trust.policies.' . static::called()->getNamespace(), []);

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
     * @return bool
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function can(): bool
    {
        if (static::isAuth()) {
            $args   = func_get_args();
            $key    = array_shift($args);
            $policies  = Registry::get('trust.policies.' . static::called()->getNamespace(), []);
            $policy   = isAke($policies, $key, false);

            if (is_callable($policy)) {
                $params = array_merge([self::user()], $args);

                if ($policy instanceof Closure) {
                    $params = array_merge([$policy], $params);

                    return instanciator()->makeClosure(...$params);
                } else {
                    if (is_array($policy)) {
                        $params = array_merge($policy, $params);

                        return instanciator()->call(...$params);
                    } else {
                        return $policy(...$params);
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param string $key
     *
     * @return bool
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function cannot(string $key): bool
    {
        return !static::can(...func_get_args());
    }

    /**
     * @param string $key
     *
     * @return bool
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function cant(string $key): bool
    {
        return static::cannot(...func_get_args());
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