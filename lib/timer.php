<?php
    namespace Octo;

    class Timer
    {
        const CMD_START = 'start';
        const CMD_STOP = 'end';

        const SECONDS = 0;
        const MILLISECONDS = 1;
        const MICROSECONDS = 2;

        const USECDIV = 1000000;

        private static $_running = false;

        private static $_queue = array();

        public static function start()
        {
            static::_pushTime(static::CMD_START);
        }

        public static function stop()
        {
            static::_pushTime(static::CMD_STOP);
        }

        public static function reset()
        {
            static::$_queue = array();
        }

        private static function _pushTime($cmd)
        {
            $mt = microtime();

            if ($cmd == static::CMD_START) {
                if (static::$_running === true) {
                    return;
                }

                static::$_running = true;

            } else if ($cmd == static::CMD_STOP) {
                if (static::$_running === false) {
                    return;
                }

                static::$_running = false;

            } else {
                return;
            }

            if ($cmd === static::CMD_START) {
                $mt = microtime();
            }

            list($usec, $sec) = explode(' ', $mt);

            $sec    = (int) $sec;
            $usec   = (float) $usec;
            $usec   = (int) ($usec * static::USECDIV);

            $time = array(
                $cmd => array(
                    'sec'   => $sec,
                    'usec'  => $usec,
                ),
            );

            if ($cmd == static::CMD_START) {
                array_push(static::$_queue, $time);
            } else if ($cmd == static::CMD_STOP) {
                $count = count(static::$_queue);
                $array =& static::$_queue[$count - 1];
                $array = array_merge($array, $time);
            }
        }

        public static function get($format = self::SECONDS)
        {
            if (static::$_running === true) {
                static::stop();
            }

            $sec    = 0;
            $usec   = 0;

            foreach (static::$_queue as $time) {
                $start = $time[static::CMD_START];
                $end = $time[static::CMD_STOP];

                $sec_diff = $end['sec'] - $start['sec'];

                if ($sec_diff === 0) {
                    $usec += ($end['usec'] - $start['usec']);

                } else {
                    $sec += $sec_diff - 1;
                    $usec += (static::USECDIV - $start['usec']) + $end['usec'];
                }
            }

            if ($usec > static::USECDIV) {
                $sec += (int) floor($usec / static::USECDIV);

                $usec = $usec % static::USECDIV;
            }

            switch ($format) {
                case static::MICROSECONDS:
                    return ($sec * static::USECDIV) + $usec;

                case static::MILLISECONDS:
                    return ($sec * 1000) + (int) round($usec / 1000, 0);

                case static::SECONDS:
                default:
                    return (float) $sec + (float) ($usec / static::USECDIV);
            }
        }

        public static function getAverage($format = self::SECONDS)
        {
            $count = count(static::$_queue);
            $sec = 0;
            $usec = static::get(static::MICROSECONDS);

            if ($usec > static::USECDIV) {
                $sec += (int) floor($usec / static::USECDIV);

                $usec = $usec % static::USECDIV;
            }

            switch ($format) {
                case static::MICROSECONDS:
                    $value = ($sec * static::USECDIV) + $usec;

                    return round($value / $count, 2);

                case static::MILLISECONDS:
                    $value = ($sec * 1000) + (int) round($usec / 1000, 0);

                    return round($value / $count, 2);

                case static::SECONDS:
                default:
                    $value = (float) $sec + (float) ($usec / static::USECDIV);

                    return round($value / $count, 2);
            }
        }

        public static function now()
        {
            return new \DateTime('now');
        }

        public static function getMS()
        {
            $mt = microtime();
            list($usec, $sec) = explode(' ', $mt);
            $mt = str_replace('.', '', ($sec + $usec));

            if (13 == strlen($mt)) {
                $mt .= '0';
            } elseif (12 == strlen($mt)) {
                $mt .= '00';
            }

            return $mt + 0;
        }
    }
