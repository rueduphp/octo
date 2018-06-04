<?php

namespace App\Services;

use Closure;
use InvalidArgumentException;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use RuntimeException;

class Log implements PsrLoggerInterface
{
    /**
     * @var MonologLogger
     */
    protected $monolog;

    /**
     * @var \Octo\Fire
     */
    protected $dispatcher;

    /**
     * @var array
     */
    protected $levels = [
        'debug'     => MonologLogger::DEBUG,
        'info'      => MonologLogger::INFO,
        'notice'    => MonologLogger::NOTICE,
        'warning'   => MonologLogger::WARNING,
        'error'     => MonologLogger::ERROR,
        'critical'  => MonologLogger::CRITICAL,
        'alert'     => MonologLogger::ALERT,
        'emergency' => MonologLogger::EMERGENCY,
    ];

    /**
     * @param  \Monolog\Logger  $monolog
     * @return void
     */
    public function __construct(MonologLogger $monolog)
    {
        $this->monolog = $monolog;

        $this->dispatcher = new \Octo\Fire('log');
    }

    /**
     * @param string $message
     * @param array $context
     * @throws \ReflectionException
     */
    public function emergency($message, array $context = [])
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @throws \ReflectionException
     */
    public function alert($message, array $context = [])
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @throws \ReflectionException
     */
    public function critical($message, array $context = [])
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @throws \ReflectionException
     */
    public function error($message, array $context = [])
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @throws \ReflectionException
     */
    public function warning($message, array $context = [])
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @throws \ReflectionException
     */
    public function notice($message, array $context = [])
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @throws \ReflectionException
     */
    public function info($message, array $context = [])
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @throws \ReflectionException
     */
    public function debug($message, array $context = [])
    {
        $this->writeLog(__FUNCTION__, $message, $context);
    }

    /**
     * @param mixed $level
     * @param string $message
     * @param array $context
     * @throws \ReflectionException
     */
    public function log($level, $message, array $context = [])
    {
        $this->writeLog($level, $message, $context);
    }

    /**
     * @param $level
     * @param $message
     * @param array $context
     * @throws \ReflectionException
     */
    public function write($level, $message, array $context = [])
    {
        $this->writeLog($level, $message, $context);
    }

    /**
     * @param $level
     * @param $message
     * @param $context
     * @throws \ReflectionException
     */
    protected function writeLog($level, $message, $context)
    {
        $this->fireLogEvent($level, $message = $this->formatMessage($message), $context);

        $this->monolog->{$level}($message, $context);
    }

    /**
     * @param $path
     * @param string $level
     * @throws \Exception
     */
    public function useFiles($path, $level = 'debug')
    {
        $this->monolog->pushHandler($handler = new StreamHandler($path, $this->parseLevel($level)));

        $handler->setFormatter($this->getDefaultFormatter());
    }

    /**
     * @param $path
     * @param int $days
     * @param string $level
     */
    public function useDailyFiles($path, $days = 0, $level = 'debug')
    {
        $this->monolog->pushHandler(
            $handler = new RotatingFileHandler($path, $days, $this->parseLevel($level))
        );

        $handler->setFormatter($this->getDefaultFormatter());
    }

    /**
     * @param string $name
     * @param string $level
     * @param int $facility
     * @return MonologLogger
     */
    public function useSyslog($name = 'laravel', $level = 'debug', $facility = LOG_USER)
    {
        return $this->monolog->pushHandler(new SyslogHandler($name, $facility, $level));
    }

    /**
     * @param string $level
     * @param int $messageType
     */
    public function useErrorLog($level = 'debug', $messageType = ErrorLogHandler::OPERATING_SYSTEM)
    {
        $this->monolog->pushHandler(
            $handler = new ErrorLogHandler($messageType, $this->parseLevel($level))
        );

        $handler->setFormatter($this->getDefaultFormatter());
    }

    /**
     * @param Closure $callback
     */
    public function listen(Closure $callback)
    {
        if (!isset($this->dispatcher)) {
            throw new RuntimeException('Events dispatcher has not been set.');
        }

        $this->dispatcher->listen(MessageLogged::class, $callback);
    }

    /**
     * @param $level
     * @param $message
     * @param array $context
     * @throws \ReflectionException
     */
    protected function fireLogEvent($level, $message, array $context = [])
    {
        if (isset($this->dispatcher)) {
            $this->dispatcher->dispatch('log.event', $level, $message, $context);
        }
    }

    /**
     * @param $message
     * @return mixed|string
     */
    protected function formatMessage($message)
    {
        if (is_array($message)) {
            return var_export($message, true);
        } elseif (\Octo\arrayable($message)) {
            return var_export($message->toArray(), true);
        }

        return $message;
    }

    /**
     * @param $level
     * @return mixed
     */
    protected function parseLevel($level)
    {
        if (isset($this->levels[$level])) {
            return $this->levels[$level];
        }

        throw new InvalidArgumentException('Invalid log level.');
    }

    /**
     * @return MonologLogger
     */
    public function getMonolog()
    {
        return $this->monolog;
    }

    /**
     * @return LineFormatter
     */
    protected function getDefaultFormatter()
    {
        return new LineFormatter(null, null, true, true);
    }

    /**
     * @return \Octo\Fire
     */
    public function getEventDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * @param $dispatcher
     */
    public function setEventDispatcher($dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }
}
