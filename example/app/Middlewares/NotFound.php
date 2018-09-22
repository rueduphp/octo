<?php
namespace App\Middlewares;

class NotFound
{
    /**
     * @return \GuzzleHttp\Psr7\Response|\Psr\Http\Message\ResponseInterface
     */
    public function process()
    {
        $container = main()->container();

        $kernel = main()->kernel();

        $tpl = $container->has('view.404') ? $container['view.404'] : function () {
            return  '<h1>Error 404</h1>';
        };

        return $kernel->response(404, [], !is_string($tpl) ? cf($tpl) : $tpl);
    }
}
