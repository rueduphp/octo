<?php
    namespace Octo;

    class Flood
    {
        public function check()
        {
            defined('APPLICATION_ENV') || define('APPLICATION_ENV', 'production');

            if ('production' == APPLICATION_ENV) {
                $ip = $this->ip();

                if (in_array($ip, ['127001', '19216811', '19216801']) || fnmatch('192168*', $ip)) {
                    return;
                }

                $this->isBanned($ip);

                $key = 'ip.' . $ip . '.' . date('dmYHi');

                $val = fmr('flood')->incr($key);

                fmr('flood')->expire($key, 60);

                if ($val > Config::get('flood.page', 30)) {
                    $this->checkedBanned($ip);

                    $this->forbidden();
                }
            }
        }

        private function isBanned($ip)
        {
            $key = 'isbanned.' . $ip;
            $row = fmr('flood')->get($key);

            if ($row) {
                $this->forbidden();
            }
        }

        private function checkedBanned($ip)
        {
            $key = 'isflood.' . $ip;
            $row = fmr('flood')->get($key);

            if (!$row) {
                fmr('flood')->set($key, 1);
            } else {
                $num = (int) $row;
                $num++;

                fmr('flood')->set($key, $num);

                if ($num >= Config::get('flood.max', 3)) {
                    $key = 'isbanned.' . $ip;
                    $row = fmr('flood')->set($key, time());
                }
            }
        }

        private function forbidden()
        {
            header('HTTP/1.1 503 Service Unavailable');

            exit;
        }

        private function ip()
        {
            return (int) str_replace('.', '', Ip::get());
        }
    }
