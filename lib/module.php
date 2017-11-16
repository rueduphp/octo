<?php
    namespace Octo;

    use \Psr\Http\Message\ServerRequestInterface;

    class Module
    {
        use Framework;

        public function run($action, ServerRequestInterface $request, Fast $app)
        {
            return callMethod($this, $action, $request, $app);
        }
    }
