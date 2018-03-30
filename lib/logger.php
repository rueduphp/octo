<?php
namespace Octo;

class Logger
{
    /**
     * @param string $type
     * @param array $arguments
     */
    public static function __callStatic(string $type, array $arguments)
    {
        $message = array_shift($arguments);

        return logFile($message, $type, appconf('dir.logs'));
    }

    /**
     * @param string $type
     * @param array $arguments
     */
    public function __call(string $type, array $arguments)
    {
        $message = array_shift($arguments);

        return logFile($message, $type, appconf('dir.logs'));
    }
}
