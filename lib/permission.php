<?php
namespace Octo;

use Psr\Http\Message\ServerRequestInterface;

class Permission implements FastPermissionInterface
{
    /**
     * @var array
     */
    protected static $booted = [];

    /**
     * @var callable
     */
    protected $resolver;

    /**
     * @var FastRules
     */
    protected $rules;

    /**
     * @param callable|null $resolver
     */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = is_null($resolver)    ? getContainer()->handled(self::class) : $resolver;

        getContainer()->define('permission', $this);
    }

    public function user()
    {
        return getContainer()->defined('user');
    }

    /**
     * @param null $rules
     *
     * @return Permission|FastRules
     */
    public function rules($rules = null)
    {
        $this->checkRules();

        if ($rules) {
            $this->rules->fill($rules);

            return $this;
        }

        return $this->rules;
    }

    /**
     * @param string $key
     * @param callable|null $value
     *
     * @return Permission|mixed
     */
    public function rule(string $key, ?callable $value = null)
    {
        $this->checkRules();

        if (!is_callable($value)) {
            return $this->rules->get($key);
        }

        $this->rules->set($key, $value);

        return $this;
    }

    /**
     * @param string $key
     *
     * @return bool
     *
     * @throws \TypeError
     */
    public function can(string $key): bool
    {
        $args   = func_get_args();
        $key    = array_shift($args);
        $rule   = $this->rule($key);

        if (is_callable($rule)) {
            $params = array_merge([$this->user()], $args);

            return $rule(...$params);
        }

        return false;
    }

    /**
     * @param string $key
     *
     * @return bool
     *
     * @throws \TypeError
     */
    public function cannot(string $key): bool
    {
        return !$this->can(...func_get_args());
    }

    /**
     * @param string $key
     *
     * @return bool
     *
     * @throws \TypeError
     */
    public function cant(string $key): bool
    {
        return $this->cannot(...func_get_args());
    }

    /**
     * @param string $key
     *
     * @return Permission
     */
    public function authorize(string $key): self
    {
        return $this->rule($key, function () {
            return true;
        });
    }

    /**
     * @param string $key
     *
     * @return Permission
     */
    public function forbid(string $key): self
    {
        return $this->rule($key, function () {
            return false;
        });
    }

    protected function checkRules()
    {
        if (!isset($this->rules)) {
            $this->rules = new FastRules;
        }
    }

    /**
     * @return Permission
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
     * @return callable
     */
    public function getResolver(): callable
    {
        return $this->resolver;
    }

    /**
     * @param callable $resolver
     *
     * @return Permission
     */
    public function setResolver(callable $resolver): self
    {
        $this->resolver = $resolver;

        return $this;
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