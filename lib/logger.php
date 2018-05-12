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

        return logFile(log_path(), $message, $type);
    }

    /**
     * @return array
     */
    public function all(): array
    {
        $rows = explode("### ", File::read(log_path() . DS . date('Y_m_d') . '.logs'));
        array_shift($rows);

        return $rows;
    }

    /**
     * @param string $type
     * @return array
     */
    public function getByType(string $type)
    {
        $type = Inflector::upper($type);

        $pattern = "*:*:*:{$type} =>*";

        return Arrays::pattern($this->all(), $pattern);
    }

    /**
     * @param string $type
     * @param array $arguments
     */
    public function __call(string $type, array $arguments)
    {
        $message = array_shift($arguments);

        return logFile(log_path(), $message, $type);
    }
}
