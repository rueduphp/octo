<?php
    namespace Octo;

    class Pipe
    {
        private $steps = [];

        public function __construct(array $steps = [])
        {
            $this->steps = $steps;
        }

        public function add($step)
        {
            $steps      = $this->steps;
            $steps[]    = $step;

            return new self($steps);
        }

        public function process($payload)
        {
            $reducer = function ($payload, $step) {
                if (is_callable($step)) {
                    return call_user_func($step, $payload);
                } else {
                    return $step->process($payload);
                }
            };

            return array_reduce(
                $this->steps,
                $reducer,
                $payload
            );
        }

        public function then($res = null)
        {
            foreach ($this->steps as $step) {
                if (is_callable($step)) {
                    $res = call_user_func_array($step, [$res]);
                    array_shift($this->steps);
                } else {
                    return $step->then($res);
                }
            }

            return $res;
        }
    }
