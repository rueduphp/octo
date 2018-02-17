<?php
    namespace Octo;

    class Cacher
    {
        private $driver;

        public function __construct($driver = null)
        {
            $driver = is_null($driver) ? fmr('cacher') : $driver;

            $this->driver = $driver;
        }

        public function getDriver()
        {
            return $this->driver;
        }

        public function setDriver($driver)
        {
            $this->driver = $driver;

            return $this;
        }

        public function __call($m, $a)
        {
            return call_user_func_array([$this->driver, $m], $a);
        }

        public static function __callStatic($m, $a)
        {
            list($driver, $m) = explode('_', Strings::uncamelize($m), 2);

            if ($driver === 'array') {
                $driver = 'now';
            } elseif ($driver === 'file') {
                $driver = 'cache';
            }

            $i = new self(lib($driver, ['cacher']));

            return call_user_func_array([$i->getDriver(), $m], $a);
        }
    }
