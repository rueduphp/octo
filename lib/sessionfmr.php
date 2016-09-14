<?php
    namespace Octo;

    class SessionFmr extends SessionAdapter implements \SessionHandlerInterface
    {
        public function __construct($ttl = 1800)
        {
            $this->handler = fmr('sessions');
            $this->ttl = $ttl;
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
