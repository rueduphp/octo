<?php
namespace Octo;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;

class Fastmiddlewarecsrf implements MiddlewareInterface
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
     * CsrfMiddleware constructor.
     *
     * @param array|\ArrayAccess $session
     * @param int                $limit
     * @param string             $sessionKey
     * @param string             $formKey
     */
    public function __construct(
        &$session,
        $limit = 50,
        $sessionKey = 'csrf.tokens',
        $formKey = '_csrf'
    ) {
        $app = actual('fast');
        $app->setSession($session);

        $this->session      = &$session;
        $this->sessionKey   = $sessionKey;
        $this->formKey      = $formKey;
        $this->limit        = $limit;
    }

    public function process(ServerRequestInterface $request, DelegateInterface $next)
    {
        if (in_array($request->getMethod(), ['PUT', 'POST', 'DELETE'], true)) {
            $params = $request->getParsedBody() ?: [];

            if (!array_key_exists($this->formKey, $params)) {
                exception('NoCsrfException', 'no csrf');
            }

            if (!in_array($params[$this->formKey], $this->session[$this->sessionKey] ?? [], true)) {
                exception('InvalidCsrfException', 'invalid csrf');
            }

            $this->removeToken($params[$this->formKey]);
        }

        return $next->process($request);
    }

    /**
     * Generate and store a random token.
     *
     * @return string
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
     * Remove a token from session.
     *
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
     * Limit the number of tokens.
     *
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
