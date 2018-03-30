<?php
namespace Octo;

class Memorylog
{
    /**
     * @var array
     */
    private static $logs = [];

    /**
     * @param string $type
     * @param array $arguments
     */
    public static function __callStatic(string $type, array $arguments)
    {
        $message = array_shift($arguments);

        static::$logs[$type][] = date('H:i:s') . ':' . $message;
    }

    /**
     * @param string $type
     * @param array $arguments
     */
    public function __call(string $type, array $arguments)
    {
        $message = array_shift($arguments);

        static::$logs[$type][] = date('H:i:s') . ':' . $message;
    }

    /**
     * @return array
     */
    public static function all(): array
    {
        return static::$logs;
    }
}
