<?php

namespace App\Services;

use DateTimeInterface;

class Cookie
{
    protected $name;
    protected $value;
    protected $domain;
    protected $expire;
    protected $path;
    protected $secure;
    protected $httpOnly;
    private $raw;
    private $sameSite;

    const SAMESITE_LAX = 'lax';
    const SAMESITE_STRICT = 'strict';

    /**
     * @param string $cookie
     * @param bool $decode
     * @return Cookie
     */
    public static function fromString(string $cookie, bool $decode = false)
    {
        $data = [
            'expires' => 0,
            'path' => '/',
            'domain' => null,
            'secure' => false,
            'httponly' => false,
            'raw' => !$decode,
            'samesite' => null,
        ];

        foreach (explode(';', $cookie) as $part) {
            if (false === strpos($part, '=')) {
                $key = trim($part);
                $value = true;
            } else {
                list($key, $value) = explode('=', trim($part), 2);
                $key = trim($key);
                $value = trim($value);
            }

            if (!isset($data['name'])) {
                $data['name'] = $decode ? urldecode($key) : $key;
                $data['value'] = true === $value ? null : ($decode ? urldecode($value) : $value);
                continue;
            }

            switch ($key = strtolower($key)) {
                case 'name':
                case 'value':
                    break;
                case 'max-age':
                    $data['expires'] = time() + (int) $value;
                    break;
                default:
                    $data[$key] = $value;
                    break;
            }
        }

        return new static(
            $data['name'],
            $data['value'],
            $data['expires'],
            $data['path'],
            $data['domain'],
            $data['secure'],
            $data['httponly'],
            $data['raw'],
            $data['samesite']
        );
    }

    /**
     * @param string $name
     * @param string|null $value
     * @param int $expire
     * @param null|string $path
     * @param string|null $domain
     * @param bool $secure
     * @param bool $httpOnly
     * @param bool $raw
     * @param string|null $sameSite
     */
    public function __construct(
        string $name,
        string $value = null,
        $expire = 0,
        ?string $path = '/',
        string $domain = null,
        bool $secure = false,
        bool $httpOnly = true,
        bool $raw = false,
        string $sameSite = null
    ) {
        if (preg_match("/[=,; \t\r\n\013\014]/", $name)) {
            throw new \InvalidArgumentException(sprintf('The cookie name "%s" contains invalid characters.', $name));
        }

        if (empty($name)) {
            throw new \InvalidArgumentException('The cookie name cannot be empty.');
        }

        if ($expire instanceof DateTimeInterface) {
            $expire = $expire->format('U');
        } elseif (!is_numeric($expire)) {
            $expire = strtotime($expire);

            if (false === $expire) {
                throw new \InvalidArgumentException('The cookie expiration time is not valid.');
            }
        }

        $this->name = $name;
        $this->value = $value;
        $this->domain = $domain;
        $this->expire = 0 < $expire ? (int) $expire : 0;
        $this->path = empty($path) ? '/' : $path;
        $this->secure = $secure;
        $this->httpOnly = $httpOnly;
        $this->raw = $raw;

        if (null !== $sameSite) {
            $sameSite = strtolower($sameSite);
        }

        if (!in_array($sameSite, array(self::SAMESITE_LAX, self::SAMESITE_STRICT, null), true)) {
            throw new \InvalidArgumentException('The "sameSite" parameter value is not valid.');
        }

        $this->sameSite = $sameSite;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $str = ($this->isRaw() ? $this->getName() : urlencode($this->getName())).'=';

        if ('' === (string) $this->getValue()) {
            $str .= 'deleted; expires='.gmdate('D, d-M-Y H:i:s T', time() - 31536001).'; max-age=-31536001';
        } else {
            $str .= $this->isRaw() ? $this->getValue() : rawurlencode($this->getValue());

            if (0 !== $this->getExpiresTime()) {
                $str .= '; expires='.gmdate('D, d-M-Y H:i:s T', $this->getExpiresTime()).'; max-age='.$this->getMaxAge();
            }
        }

        if ($this->getPath()) {
            $str .= '; path='.$this->getPath();
        }

        if ($this->getDomain()) {
            $str .= '; domain='.$this->getDomain();
        }

        if (true === $this->isSecure()) {
            $str .= '; secure';
        }

        if (true === $this->isHttpOnly()) {
            $str .= '; httponly';
        }

        if (null !== $this->getSameSite()) {
            $str .= '; samesite='.$this->getSameSite();
        }

        return $str;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return null|string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return null|string
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * @return int
     */
    public function getExpiresTime()
    {
        return $this->expire;
    }

    /**
     * @return int
     */
    public function getMaxAge()
    {
        return 0 !== $this->expire ? $this->expire - time() : 0;
    }

    /**
     * @return null|string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return bool
     */
    public function isSecure()
    {
        return $this->secure;
    }

    /**
     * @return bool
     */
    public function isHttpOnly()
    {
        return $this->httpOnly;
    }

    /**
     * @return bool
     */
    public function isCleared()
    {
        return $this->expire < time();
    }

    /**
     * @return bool
     */
    public function isRaw()
    {
        return $this->raw;
    }

    /**
     * @return null|string
     */
    public function getSameSite()
    {
        return $this->sameSite;
    }

    /**
     * @param $name
     * @param $value
     * @param int $minutes
     * @param null $path
     * @param null $domain
     * @param null $secure
     * @param bool $httpOnly
     * @param bool $raw
     * @param null $sameSite
     * @return Cookie
     */
    public function make(
        $name,
        $value,
        $minutes = 0,
        $path = null,
        $domain = null,
        $secure = null,
        $httpOnly = true,
        $raw = false,
        $sameSite = null
    ) {
        list($path, $domain, $secure, $sameSite) = $this->getPathAndDomain($path, $domain, $secure, $sameSite);

        $time = ($minutes === 0) ? 0 : $this->availableAt($minutes * 60);

        return new static($name, $value, $time, $path, $domain, $secure, $httpOnly, $raw, $sameSite);
    }

    /**
     * @param $name
     * @param $value
     * @param null $path
     * @param null $domain
     * @param null $secure
     * @param bool $httpOnly
     * @param bool $raw
     * @param null $sameSite
     * @return Cookie
     */
    public function forever(
        $name,
        $value,
        $path = null,
        $domain = null,
        $secure = null,
        $httpOnly = true,
        $raw = false,
        $sameSite = null
    ) {
        return $this->make($name, $value, 2628000, $path, $domain, $secure, $httpOnly, $raw, $sameSite);
    }

    /**
     * @param $name
     * @param null $path
     * @param null $domain
     * @return Cookie
     */
    public function forget($name, $path = null, $domain = null)
    {
        return $this->make($name, null, -2628000, $path, $domain);
    }

    /**
     * @param $path
     * @param $domain
     * @param null $secure
     * @param null $sameSite
     * @return array
     */
    protected function getPathAndDomain($path, $domain, $secure = null, $sameSite = null)
    {
        return [$path ?: $this->path, $domain ?: $this->domain, is_bool($secure) ? $secure : $this->secure, $sameSite ?: $this->sameSite];
    }

    /**
     * @param $path
     * @param $domain
     * @param bool $secure
     * @param null $sameSite
     * @return $this
     */
    public function setDefaultPathAndDomain($path, $domain, $secure = false, $sameSite = null)
    {
        list($this->path, $this->domain, $this->secure, $this->sameSite) = [$path, $domain, $secure, $sameSite];

        return $this;
    }

    protected function availableAt($delay = 0)
    {
        $delay = parseDateInterval($delay);

        return $delay instanceof DateTimeInterface
            ? $delay->getTimestamp()
            : now()->addSeconds($delay)->getTimestamp();
    }
}
