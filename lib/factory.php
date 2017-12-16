<?php
namespace Octo;

use function get_parent_class;

class Factory
{
    /**
     * @var array
     */
    private static $factories = [];

    /**
     * @var string
     */
    private static $language = 'fr_FR';

    /**
     * @param string $className
     * @param callable $callable
     */
    public static function for(string $className, callable $callable): void
    {
        self::$factories[$className] = $callable;
    }

    /**
     * @param string $className
     * @param int $count
     *
     * @return array
     */
    public static function make(string $className, int $count = 1): array
    {
        $rows = [];

        $callable = isAke(self::$factories, $className, false);

        if (is_callable($callable)) {
            while ($count > 0) {
                $count--;
                $rows[] = $callable(faker());
            }
        }

        return $rows;
    }

    /**
     * @param string $className
     * @param int $count
     *
     * @return array
     */
    public static function save(string $className, int $count = 1): array
    {
        $entities   = [];
        $entity     = new $className;
        $rows       = self::make($className, $count);

        $parent = get_parent_class($entity);

        $method = in_array(
            $parent, [Octal::class, Bank::class]
        ) ? 'store' : 'create';

        foreach ($rows as $row) {
            $entities[] = $entity->{$method}($row);
        }

        return $entities;
    }

    /**
     * @return array
     */
    public static function getFactories(): array
    {
        return self::$factories;
    }

    /**
     * @param array $factories
     */
    public static function setFactories(array $factories): void
    {
        self::$factories = $factories;
    }

    /**
     * @param string $language
     */
    public static function setLanguage(string $language): void
    {
        self::$language = $language;
    }
}
