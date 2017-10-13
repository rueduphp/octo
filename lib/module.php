<?php
    namespace Octo;

    use \Psr\Http\Message\ServerRequestInterface;

    class Module
    {
        public function run($action, ServerRequestInterface $request, Fast $app)
        {
            return callMethod($this, $action, $request, $app);
        }

        public function __call($m, $a)
        {
            $method = '\\Octo\\' . $m;

            if (function_exists($method)) {
                return call_user_func_array($method, $a);
            }
        }
    }
