<?php
namespace Octo;

class Fastmiddlewareredirect
{
    use FastTrait;

    public function process()
    {
        $container  = $this->getContainer();
        $route      = $container->define('route');

        if ($route) {
            $url = $container->define('redirects.routes.' . $route->getName());

            if ($url) {
                return $container->redirectResponse($url);
            }
        }

        $request = $container->getRequest();

        return $container->process($request);
    }
}