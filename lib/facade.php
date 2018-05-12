<?php
namespace Octo;

use RuntimeException;

class Facade
{
    /** @var In */
    protected static $app;

    /**
     * @param string $method
     * @param array $args
     * @return mixed|null
     */
    public static function __callStatic(string $method, array $args)
    {
        if (null === static::$app) {
            static::$app = In::self();
        }

        $in = static::$app;

        try {
            $instance = null;

            $class = static::getNativeClass();

            if ($in::has($class)) {
                $instance = $in::get($class);
            } else {
                if (class_exists($class)) {
                    $instance = gi()->make($class);
                    $in::setInstance($instance);
                }
            }

            if (!is_object($instance) || !$instance) {
                throw new RuntimeException(get_called_class() . ' has not been set.');
            }

            $params = array_merge([$instance, $method], $args);

            return gi()->call(...$params);
        } catch (\Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * @return string
     */
    private static function getNativeClass(): string
    {
        throw new RuntimeException(get_called_class() . ' does not implement getNativeClass method.');
    }
}
