<?php
namespace Octo;

class Url
{
    /**
     * @param bool $use_forwarded_host
     * @return string
     */
    public static function root($use_forwarded_host = false ): string
    {
        $s        = $_SERVER;
        $ssl      = (!empty($s['HTTPS']) && $s['HTTPS'] === 'on');
        $sp       = strtolower($s['SERVER_PROTOCOL']);
        $protocol = substr($sp, 0, strpos($sp, '/' )) . (($ssl) ? 's' : '' );
        $port     = $s['SERVER_PORT'];
        $port     = ((!$ssl && $port === '80') || ($ssl && $port === '443')) ? '' : ':' . $port;

        $host     = ($use_forwarded_host && isset($s['HTTP_X_FORWARDED_HOST']))
            ? $s['HTTP_X_FORWARDED_HOST']
            : (isset($s['HTTP_HOST'])
                ? $s['HTTP_HOST']
                : null)
        ;

        $host  = isset($host) ? $host : $s['SERVER_NAME'] . $port;

        return $protocol . '://' . $host;
    }

    /**
     * @param bool $use_forwarded_host
     * @return string
     */
    public static function full($use_forwarded_host = false): string
    {
        return static::root($use_forwarded_host) . $_SERVER['REQUEST_URI'];
    }

    /**
     * @param bool $withQuery
     * @return string
     * @throws \ReflectionException
     */
    public static function get(bool $withQuery = false): string
    {
        $uri = getRequest()->getUri();

        $url = '';

        $url .= $uri->getScheme() . '://';
        $url .= $uri->getHost();

        $port = $uri->getPort();

        if (null !== $port) {
            $url .= ':' . $port;
        }

        $url .= $uri->getPath();

        if (true === $withQuery) {
            $url .= '?' . $uri->getQuery();
        }

        return $url;
    }

    /**
     * @return string
     * @throws \ReflectionException
     */
    public function __invoke(): string
    {
        $uri = getRequest()->getUri();

        $url = '';

        $url .= $uri->getScheme() . '://';
        $url .= $uri->getHost();

        $port = $uri->getPort();

        if (null !== $port) {
            $url .= ':' . $port;
        }

        return $url;
    }

    /**
     * @return string
     */
    public static function self(): string
    {
        return (new static)();
    }

    /**
     * @return string
     */
    public static function base(): string
    {
        return (new static)();
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return (new static)();
    }

    /**
     * @param string $name
     * @param array $parameters
     * @return mixed
     * @throws \ReflectionException
     */
    public function __call(string $name, array $parameters)
    {
        $uri = getRequest()->getUri();

        return $uri->{$name}(...$parameters);
    }

    /**
     * @param string $name
     * @param array $parameters
     * @return mixed
     */
    public static function __callStatic(string $name, array $parameters)
    {
        $self = new static;

        return $self->{$name}(...$parameters);
    }
}