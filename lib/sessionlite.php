<?php
    namespace Octo;

    require_once (__DIR__ . '/sessionadapter.php');

    class SessionLite extends SessionAdapter implements \SessionHandlerInterface
    {
        public function __construct($ttl = 1800, $prefix = 'octosession')
        {
            $this->handler = lite('sessions');
            $this->ttl = $ttl;
            $this->prefix = $prefix;
        }

        public function read($id)
        {
            return $this->handler->get($this->prepareId($id));
        }

        public function write($id, $data)
        {
            $this->handler->set($this->prepareId($id), $data, (int) $this->ttl);
        }

        public function destroy($id)
        {
            $this->handler->del($this->prepareId($id));
        }

        public function gc($maxLifetime)
        {
            $this->handler->clean();
        }
    }
