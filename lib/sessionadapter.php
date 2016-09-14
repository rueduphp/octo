<?php
    namespace Octo;

    abstract class SessionAdapter
    {
        protected $handler = null;

        protected $prefix = 'PHPSESSID:';

        protected $ttl = 1800;

        protected function prepareId($id)
        {
            return $this->prefix . $id;
        }

        public function open($savePath, $sessionName)
        {
            $this->prefix = $sessionName . ':';
            $this->ttl = (int) ini_get('session.gc_maxlifetime');
        }

        public function close()
        {
            $this->handler = null;
            unset($this->handler);
        }
    }
