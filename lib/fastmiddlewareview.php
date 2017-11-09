<?php
namespace Octo;

use function Http\Response\send;

class Fastmiddlewareview
{
    use FastTrait;

    public function process()
    {
        $container  = $this->getContainer();
        $route      = $container->define('route');

        if ($route) {
            $file = $container->define('views.routes.' . $route->getName());

            if ($file) {
                $renderer = $container->getRenderer();

                return $container->response(
                    200,
                    [],
                    $renderer->render($file)
                );
            }
        }

        $request = $container->getRequest();

        return $container->process($request);
    }
}