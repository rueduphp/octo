<?php
namespace Octo;

class Url
{
    /**
     * @param bool $use_forwarded_host
     * @return string
     */
    public static function root($use_forwarded_host = false )
    {
        $s        = $_SERVER;
        $ssl      = (!empty($s['HTTPS']) && $s['HTTPS'] == 'on');
        $sp       = strtolower($s['SERVER_PROTOCOL']);
        $protocol = substr($sp, 0, strpos($sp, '/' )) . (($ssl) ? 's' : '' );
        $port     = $s['SERVER_PORT'];
        $port     = ((!$ssl && $port == '80') || ($ssl && $port=='443')) ? '' : ':' . $port;

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
    public static function full($use_forwarded_host = false)
    {
        return static::root($use_forwarded_host) . $_SERVER['REQUEST_URI'];
    }
}