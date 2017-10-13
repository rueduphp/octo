<?php
    namespace Octo;

    class Dispatch
    {
        protected $position = 0;
        protected $actions = [];

        public function add($concern)
        {
            if (is_string($concern)) {
                $concern = [foundry($concern), 'handle'];
            }

            $this->actions[] = $concern;

            return $this;
        }

        public function handle($request = null, $response = null)
        {
            $request    = is_null($request)  ? context('app')->request() : $request;
            $response   = is_null($response) ? context('app')->response() : $response;

            $callable = $this->getNext();

            $this->position++;

            if (!is_callable($callable)) {
                return $response;
            }

            return call_user_func_array(
                $callable, [
                    $request,
                    $response, [
                        $this,
                        'handle'
                    ]
                ]
            );
        }

        protected function getNext()
        {
            return Arrays::get(
                $this->actions,
                $this->position,
                null
            );
        }
    }
