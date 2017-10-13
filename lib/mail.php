<?php
    namespace Octo;

    class Mail
    {
        public static function send($config)
        {
            $config = arrayable($config) ? $config->toArray() : $config;

            return mailto($config);
        }
    }
