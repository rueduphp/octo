<?php
    namespace Octo;

    require_once (__DIR__ . '/sessionadapter.php');

    class SessionRedis extends SessionAdapter implements \SessionHandlerInterface
    {
        public function __construct($ttl = 1800)
        {
            $this->handler = redis('sessions');
            $this->ttl = $ttl;
        }

        public function read($id)
        {
            return $this->handler->get($this->prepareId($id));
        }

        public function write($id, $data)
        {
            $key = $this->prepareId($id);
            $this->handler->set($key, $data, (int) $this->ttl);
            $this->handler->expire($key, (int) $this->ttl);
        }

        public function destroy($id)
        {
            $this->handler->del($this->prepareId($id));
        }

        public function gc($maxLifetime)
        {
            return true;
        }
    }
