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
     * @param FastRequest $request
     * @throws \ReflectionException
     */
    public function __construct(FastRequest $request, FastRedirector $redirect)
    {
        $this->request  = $request;
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
        if ($route = $this->request->route()) {
            $routeName = $route->name;

            $excepts = [
                'login',
                'log',
                'forgot',
                'social.github',
                'social.linkedin',
                'social.google',
                'social.facebook',
                'social.twitter',
            ];

            if (!$this->request->auth()->logged() && !in_array($routeName, $excepts)) {
                $found  = false;
                $user   = model($this->request->session()->getUserModel())
                    ->whereNotNull('remember_token')
                    ->whereRememberToken(\Octo\forever())
                    ->orderByDesc('logged_at')
                    ->first()
                ;

                if (null !== $user) {
                    repo('user')->connect($user);
                    $found = true;
                }

                if ('GET' === $this->request->method()) {
                    $this->request->session()->set('redirect_url', Url::get(true));
                } else {
                    $this->request->session()->set(
                        'redirect_url',
                        $this->request->post(
                            'redirect_url',
                            $this->request->session()->get('redirect_url', Url::get(true))
                        )
                    );
                }

                if (false === $found) {
                    return $this->redirect->to('login');
                }
            }
        }

        return $delegate->process($request);
    }
}
