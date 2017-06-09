<?php
    namespace Octo;

    class Mail
    {
        public static function send($config)
        {
            $config = !is_array($config) ? $config->toArray() : $config;

            return mailto($config);
        }
    }
