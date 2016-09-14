<?php
    namespace Octo;

    class Piper
    {
        protected $action;
        protected $steps = [];
        protected $method = 'run';

        private function parts()
        {
            return function ($step, $steps) {
                return function ($action) use ($step, $steps) {
                    if ($steps instanceof Closure) {
                        return call_user_func($steps, $action, $step);
                    } elseif (! is_object($steps)) {
                        list($name, $params) = $this->parse($steps);

                        $steps = lib('app')->make($name);

                        $params = array_merge([$action, $step], $params);
                    } else {
                        $params = [$action, $step];
                    }

                    return call_user_func_array([$steps, $this->method], $params);
                };
            };
        }

        private function parse($line)
        {
            list($name, $params) = array_pad(
                explode(
                    ':',
                    $line,
                    2
                ),
                2,
                []
            );

            if (is_string($params)) {
                $params = explode(',', $params);
            }

            return [$name, $params];
        }

        private function first(Closure $resolver)
        {
            return function ($action) use ($resolver) {
                return call_user_func($resolver, $action);
            };
        }

        public function send($action)
        {
            $this->action = $action;

            return $this;
        }

        public function through($steps)
        {
            $this->steps = is_array($steps) ? $steps : func_get_args();

            return $this;
        }

        public function runner($method)
        {
            $this->method = $method;

            return $this;
        }

        public function then(Closure $resolver)
        {
            $firstStep = $this->first($resolver);

            $steps = array_reverse($this->steps);

            return call_user_func(
                array_reduce(
                    $steps,
                    $this->parts(),
                    $firstStep
                ),
                $this->action
            );
        }
    }
