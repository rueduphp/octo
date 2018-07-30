<?php
namespace App\Middlewares;

use Interop\Http\ServerMiddleware\DelegateInterface;
use function Octo\aget;
use function Octo\app_path;
use function Octo\fast;
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

        if (false === $debug) {
            try {
                return $delegate->process($request);
            } catch (\Exception $e) {
                return $this->handle($e);
            }
        }

        return $delegate->process($request);
    }

    /**
     * @param $exception
     * @return \GuzzleHttp\Psr7\Response
     * @throws \ReflectionException
     */
    public function handle($exception)
    {
        return fast()->response(500, [], render(app_path('views/static/error.php')));
    }
}
