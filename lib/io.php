<?php
    namespace Octo;

    use ElephantIO\Client, ElephantIO\Engine\SocketIO\Version1X;

    class Ip
    {
        private $client;

        public function __construct($server = null)
        {
            $server         = is_null($server) ? 'http://localhost:4545' : $server;
            $this->client   = (new Client(new Version1X($server)))->initialize();
        }

        public function getClient()
        {
            return $this->client;
        }

        public function close()
        {
            $this->client->close();
        }

        public function __invoke()
        {
            return $this->client;
        }

        public function __call($m, $a)
        {
            return call_user_func_array([$this->client, $m], $a);
        }
    }
