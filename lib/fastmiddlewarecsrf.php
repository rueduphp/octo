<?php
namespace Octo;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Psr\Http\Message\ServerRequestInterface;

class Fastmiddlewarecsrf extends FastMiddleware
{
    /**
     * @var array|\ArrayAccess
     */
    private $session;

    /**
     * @var string
     */
    private $sessionKey;

    /**
     * @var string
     */
    private $formKey;

    /**
     * @var int
     */
    private $limit;

    /**
     * @param $session
     * @param int $limit
     * @param string $sessionKey
     * @param string $formKey
     * @throws \ReflectionException
     */
    public function __construct(
        &$session,
        $limit = 50,
        $sessionKey = 'csrf.tokens',
        $formKey = '_csrf'
    ) {
        $app = $this->getContainer();
        $app->setSession($session);

        $this->session      = &$session;
        $this->sessionKey   = $sessionKey;
        $this->formKey      = $formKey;
        $this->limit        = $limit;
    }

    /**
     * @param ServerRequestInterface $request
     * @param DelegateInterface $next
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, ?DelegateInterface $next = null)
    {
        if (in_array($request->getMethod(), ['PUT', 'POST', 'DELETE'], true)) {
            $params = $request->getParsedBody() ?: [];

            if (!array_key_exists($this->formKey, $params)) {
                exception('NoCsrf', 'no csrf');
            }

            if (false === $this->check($params[$this->formKey], $this->session[$this->sessionKey] ?? [])) {
                exception('InvalidCsrf', 'invalid csrf');
            }

            $this->removeToken($params[$this->formKey]);
        }

        return $next->process($request);
    }

    /**
     * @param $csrf
     * @param $tokens
     *
     * @return bool
     */
    private function check($csrf, $tokens): bool
    {
        foreach ($tokens as $token) {
            if ($csrf === $token) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function generateToken()
    {
        $token      = bin2hex(random_bytes(16));
        $tokens     = $this->session[$this->sessionKey] ?? [];
        $tokens[]   = $token;

        $this->session[$this->sessionKey] = $this->limitTokens($tokens);

        return $token;
    }

    /**
     * @param string $token
     */
    private function removeToken($token)
    {
        $this->session[$this->sessionKey] = array_filter(
            $this->session[$this->sessionKey] ?? [],
            function ($t) use ($token) {
                return $token !== $t;
            }
        );
    }

    /**
     * @return string
     */
    public function getSessionKey()
    {
        return $this->sessionKey;
    }

    /**
     * @return string
     */
    public function getFormKey()
    {
        return $this->formKey;
    }

    /**
     * @param array $tokens
     *
     * @return array
     */
    private function limitTokens(array $tokens)
    {
        if (count($tokens) > $this->limit) {
            array_shift($tokens);
        }

        return $tokens;
    }
}
