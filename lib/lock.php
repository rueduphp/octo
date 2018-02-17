<?php
namespace Octo;

use Psr\Http\Message\ServerRequestInterface;

class Lock implements FastAuthInterface
{
    use Macroable;

    /**
     * @var array
     */
    protected static $booted = [];

    /**
     * @var string
     */
    protected $login_path;

    /**
     * @var string
     */
    protected $logout_path;
    /**
     * @var string
     */
    protected $key;

    /**
     * @var callable
     */
    protected $resolver;

    /**
     * @param string $key
     * @param null|callable $resolver
     */
    public function __construct(string $key = 'user', ?callable $resolver = null)
    {
        $this->key      = $key;
        $this->resolver = is_null($resolver) ? getContainer()->handled(self::class) : $resolver;

        $this->user();

        getContainer()->define('lock', $this);
    }

    /**
     * @return mixed|null
     *
     * @throws \TypeError
     */
    public function user()
    {
        $user = getContainer()->defined('user');

        if (is_null($user)) {
            $user = $this->getSession()[$this->getKey()];
            $user = arrayable($user) ? $user->toArray() : $user;

            getContainer()->define('user', $user);
        }

        return $user;
    }

    /**
     * @return Lock
     */
    protected static function called()
    {
        $class = get_called_class();

        $instance = isAke(self::$booted, $class, null);

        if (!$instance) {
            $instance = instanciator()->singleton($class);

            self::$booted[$class] = $instance;
        }

        return $instance;
    }

    /**
     * @return bool
     */
    public static function connect(): bool
    {
        /** @var Lock $self */
        $self = self::called();

        getContainer()->define('user', null);

        $resolver = $self->getResolver();

        $args = array_merge(['login', $self], func_get_args());

        $logged = $resolver(...$args);

        if (false !== $logged) {
            getContainer()->define('user', $logged);

            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public static function disconnect(): bool
    {
        /** @var Lock $self */
        $self = self::called();

        $resolver = $self->getResolver();

        $args = array_merge(['logout', $self], func_get_args());

        $logged = $resolver(...$args);

        if (false !== $logged) {
            getContainer()->define('user', null);

            return true;
        }

        return false;
    }

    /**
     * @return mixed
     *
     * @throws \TypeError
     */
    public static function get()
    {
        $args = func_get_args();
        $field = array_shift($args);
        $user = self::called()->user();

        if ($field && $user) {
            if (is_array($user) || is_object($user)) {
                return isAke($user, $field, null);
            }
        }

        return $user;
    }

    /**
     * @return array
     */
    public static function getBooted(): array
    {
        return self::$booted;
    }

    /**
     * @param string $username
     * @param string $password
     *
     * @return bool
     *
     * @throws \TypeError
     */
    public function login(string $username, string $password): bool
    {
        $logged = $this->resolver('login', $this, $username, $password);

        if (false !== $logged) {
            return true;
        }

        return false;
    }

    public function logout()
    {
        $status = $this->resolver('logout', $this);

        if (false !== $status) {
            return true;
        }

        return false;
    }

    /**
     * @param null|string $field
     *
     * @return mixed
     *
     * @throws \TypeError
     */
    public function getUser(?string $field = null)
    {
        $user = $this->user();

        if ($field && $user) {
            if (is_array($user) || is_object($user)) {
                return isAke($user, $field, null);
            }
        }

        return $user;
    }

    /**
     * @return Session
     *
     * @throws \TypeError
     */
    public function getSession()
    {
        return getContainer()->getSession();
    }

    /**
     * @return ServerRequestInterface
     */
    public function getRequest()
    {
        return getContainer()->getRequest();
    }

    /**
     * @return string
     */
    public function getLoginPath()
    {
        return $this->login_path;
    }

    /**
     * @return string
     */
    public function getLogoutPath()
    {
        return $this->logout_path;
    }

    /**
     * @return string
     */
    public static function loginPath()
    {
        return self::called()->getLoginPath();
    }

    /**
     * @return string
     */
    public static function logoutPath()
    {
        return self::called()->getLogoutPath();
    }

    /**
     * @param string $path
     *
     * @return Lock
     */
    public function setLoginPath(string $path): self
    {
        $this->login_path = $path;

        return $this;
    }

    /**
     * @param string $path
     *
     * @return Lock
     */
    public function setLogoutPath(string $path): self
    {
        $this->logout_path = $path;

        return $this;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @param string $key
     *
     * @return Lock
     */
    public function setKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    /**
     * @return callable
     */
    public function getResolver(): callable
    {
        return $this->resolver;
    }

    /**
     * @param callable $resolver
     *
     * @return Lock
     */
    public function setResolver(callable $resolver): self
    {
        $this->resolver = $resolver;

        return $this;
    }

    /**
     * @return Fast
     */
    public function app()
    {
        return getContainer();
    }

    /**
     * @param string $method
     * @param array $parameters
     *
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters)
    {
        $self = self::called();

        $resolver = $self->getResolver();

        $args = array_merge([$method, $self], func_get_args());

        return $resolver(...$args);
    }
}
