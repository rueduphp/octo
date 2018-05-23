<?php
namespace App\Middlewares;

use GuzzleHttp\Psr7\MessageTrait;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Octo\FastMiddleware;
use Octo\FastRedirector;
use Octo\FastRequest;
use Octo\Url;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Gate extends FastMiddleware
{
    /**
     * @var FastRequest
     */
    private $request;

    /**
     * @var FastRedirector
     */
    private $redirect;

    /**
     * @var null|\Octo\FastObject
     */
    private $route;

    /**
     * @param FastRequest $request
     * @throws \ReflectionException
     */
    public function __construct(FastRequest $request, FastRedirector $redirect)
    {
        $this->request  = $request;
        $this->route    = $request->route();
        $this->redirect = $redirect;
    }

    /**
     * @param ServerRequestInterface $request
     * @param DelegateInterface $delegate
     * @return MessageTrait|ResponseInterface
     * @throws \Octo\Exception
     * @throws \ReflectionException
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        if (null !== $this->route) {
            $routeName = $this->route->name;

            $excepts = ['login', 'log', 'forgot'];

            if (!$this->request->auth()->logged() && !in_array($routeName, $excepts)) {
                $found  = false;
                $user   = model($this->request->session()->getUserModel())
                    ->whereRememberToken(\Octo\forever())
                    ->orderByDesc('logged_at')
                    ->first()
                ;

                if (null !== $user) {
                    repo('user')->connect($user);
                    $found = true;
                }

                if (false === $found) {
                    if ($this->request->method() === 'GET') {
                        $this->request->session()->set('redirect_url', Url::get(true));
                    }

                    return $this->redirect->to('login');
                }
            }
        }

        return $delegate->process($request);
    }
}