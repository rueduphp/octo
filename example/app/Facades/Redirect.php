<?php
namespace App\Facades;

use Octo\Facades\Redirect as Redirector;
use Octo\FastRedirector;
use function Octo\gi;

class Redirect extends Redirector
{
    private static $self;

    /**
     * @return FastRedirector
     * @throws \ReflectionException
     */
    private static function self()
    {
        if (null === static::$self) {
            static::$self = gi()->make(FastRedirector::class);
        }

        return static::$self;
    }

    /**
     * @param string $message
     * @return FastRedirector
     * @throws \ReflectionException
     */
    public static function success(string $message)
    {
        flash()->success($message);

        return static::self();
    }

    /**
     * @param string $message
     * @return FastRedirector
     * @throws \ReflectionException
     */
    public static function error(string $message)
    {
        flash()->error($message);

        return static::self();
    }

    /**
     * @param string $message
     * @return FastRedirector
     * @throws \ReflectionException
     */
    public static function warning(string $message)
    {
        flash()->warning($message);

        return static::self();
    }

    /**
     * @param string $message
     * @return FastRedirector
     * @throws \ReflectionException
     */
    public static function info(string $message)
    {
        flash()->info($message);

        return static::self();
    }

    /**
     * @param array $data
     * @return FastRedirector
     * @throws \ReflectionException
     */
    public static function with(array $data)
    {
        redirectWith($data);

        return static::self();
    }
}