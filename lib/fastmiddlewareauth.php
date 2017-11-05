<?php
    namespace Octo;

    use Interop\Http\ServerMiddleware\DelegateInterface;
    use Psr\Http\Message\ServerRequestInterface;

    class Fastmiddlewareauth extends FastMiddleware
    {
        public function process(ServerRequestInterface $request, DelegateInterface $next)
        {
            /** @var Fast $app */
            $app    = $this->getContainer();
            $auth   = $app->getAuth();

            if ($auth) {
                $user = $auth->getUser();

                if ($user) {
                    $app->setUser($user);

                    return $next->process($request);
                }
            }

            exception('authmiddleware', '');
        }
    }
