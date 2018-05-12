<?php
namespace Octo;

class Fastmiddlewareredirect
{
    use FastTrait;

    /**
     * @return \GuzzleHttp\Psr7\Response|mixed|null|\Psr\Http\Message\ResponseInterface
     * @throws \ReflectionException
     */
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