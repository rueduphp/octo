<?php
namespace App\Middlewares;

use Interop\Http\ServerMiddleware\DelegateInterface;
use function Octo\aget;
use function Octo\app_path;
use Octo\Facades\Config;
use Octo\FastMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Exception extends FastMiddleware
{
    /**
     * @param ServerRequestInterface $request
     * @param DelegateInterface $delegate
     * @return \GuzzleHttp\Psr7\Response|ResponseInterface
     * @throws \ReflectionException
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $config = Config::get('app', []);
        $env    = aget($config, 'env', 'production');

        $debug = $env !== 'production';

        if (true === $debug) {
            return $delegate->process($request);
        } else {
            try {
                return $delegate->process($request);
            } catch (\Exception $e) {
                return $this->handle($e);
            }
        }
    }

    /**
     * @param $exception
     * @return \GuzzleHttp\Psr7\Response
     * @throws \ReflectionException
     */
    public function handle($exception)
    {
        $content = render(app_path('views/static/error.php'));

        return \Octo\fast()->response(500, [], $content);
    }
}
