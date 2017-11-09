<?php
    namespace Octo;

    abstract class SessionAdapter
    {
        /**
         * @var mixed|null
         */
        protected $handler = null;

        /**
         * @var string
         */
        protected $prefix = 'PHPSESSID:';

        /**
         * @var int
         */
        protected $ttl = 1800;

        protected function prepareId($id)
        {
            return $this->prefix . $id;
        }

        public function open($savePath, $sessionName)
        {
            $this->prefix   = $sessionName . ':';
            $this->ttl      = (int) ini_get('session.gc_maxlifetime');

            return true;
        }

        public function close()
        {
            $this->handler = null;
            unset($this->handler);
        }
    }
