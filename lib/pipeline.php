<?php
    namespace Octo;

    class Pipeline
    {
        private $queues = [];

        public function __construct()
        {
            register_shutdown_function([&$this, 'dequeue']);
        }

        public function push($name, $cb = null)
        {
            if (null === $cb && is_callable($name)) {
                $cb     = $name;
                $name   = 'core';
            }

            if (!is_callable($cb)) {
                throw new Exception('Callback must be callable.');
            }

            if (!isset($this->queues[$name])) {
                $this->queues[$name] = new SplQueue();
            }

            $this->queues[$name]->enqueue($cb);

            return $this;
        }

        public function listen($name = 'core')
        {
            $commands = isAke($this->queues, $name, null);

            if ($commands) {
                while (!$commands->isEmpty()) {
                    $command = $commands->dequeue();
                    $command();
                }
            }
        }

        public function dequeue()
        {
            if (!empty($this->queues)) {
                foreach ($this->queues as $bucket => $commands) {
                    $res = null;

                    while (!$commands->isEmpty()) {
                        $command    = $commands->dequeue();
                        $res        = $command($res);
                    }
                }
            }
        }

        public function __call($m, $a)
        {
            if (!empty($a)) {
                return $this->push($m, current($a));
            } else {
                $commands = isAke($this->queues, $m, null);

                if ($commands) {
                    $res = null;

                    while (!$commands->isEmpty()) {
                        $command    = $commands->dequeue();
                        $res        = $command($res);
                    }

                    unset($this->queues[$m]);

                    return $res;
                }

                return $this;
            }
        }
    }
