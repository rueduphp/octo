<?php
namespace Octo\Facades;

use Octo\Facade;

class Request extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'request';
    }
}


class Lang extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'lang';
    }
}

class Memory extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'memory';
    }
}

class Redys extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'redis';
    }
}

class Db extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'db';
    }
}

class View extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'view';
    }
}

class Session extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'sessions';
    }
}

class Instant extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'instant';
    }
}

class Config extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'config';
    }
}

class Bdd extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'bdd';
    }
}

class Validator extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'validator';
    }
}

class Cache extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'cache';
    }
}

class Routing extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'router';
    }
}

class Mail extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'mail';
    }
}

class Route
{
    protected static $router;

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed|null
     * @throws \ReflectionException
     */
    public static function __callStatic(string $name, array $arguments)
    {
        if (null === static::$router) {
            static::$router = \Octo\Setup::route();
        }

        $parameters = array_merge([static::$router, $name], $arguments);

        return \Octo\gi()->call(...$parameters);
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function __call(string $name, array $arguments)
    {
        if (null === static::$router) {
            static::$router = \Octo\Setup::route();
        }

        $parameters = array_merge([static::$router, $name], $arguments);

        return \Octo\gi()->call(...$parameters);
    }
}

class Path
{
    private static $paths;

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed|null
     * @throws \ReflectionException
     */
    public static function __callStatic(string $name, array $arguments)
    {
        if (null === static::$paths) {
            static::$paths = \Octo\Setup::path();
        }

        $parameters = array_merge([static::$paths, $name], $arguments);

        return \Octo\gi()->call(...$parameters);
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function __call(string $name, array $arguments)
    {
        if (null === static::$paths) {
            static::$paths = \Octo\Setup::path();
        }

        $parameters = array_merge([static::$paths, $name], $arguments);

        return \Octo\gi()->call(...$parameters);
    }
}

class Rights
{
    private static $rights;

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed|null
     * @throws \ReflectionException
     */
    public static function __callStatic(string $name, array $arguments)
    {
        if (null === static::$rights) {
            static::$rights = \Octo\Setup::rights();
        }

        $parameters = array_merge([static::$rights, $name], $arguments);

        return \Octo\gi()->call(...$parameters);
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function __call(string $name, array $arguments)
    {
        if (null === static::$rights) {
            static::$rights = \Octo\Setup::rights();
        }

        $parameters = array_merge([static::$rights, $name], $arguments);

        return \Octo\gi()->call(...$parameters);
    }
}

class Renderer extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'renderer';
    }
}

class Html extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'html';
    }
}

class Form extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'form';
    }
}

class ES extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'elasticsearch';
    }
}

class Flash extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'flash';
    }
}

class Redirect extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'redirect';
    }
}

class Auth extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'auth';
    }
}

class User extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'user';
    }
}

class Log extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'log';
    }
}

class Event extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'event';
    }
}

class Setup extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return 'setup';
    }
}

class Is extends Facade
{
    /**
     * @return string
     */
    public static function getNativeClass(): string
    {
        return \Octo\Is::class;
    }
}
