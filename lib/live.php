<?php
namespace Octo;

use ArrayAccess;
use Psr\Http\Message\ServerRequestInterface;

class Live implements ArrayAccess, FastSessionInterface
{
    /**
     * @var Cache
     */
    private $driver;

    /**
     * @var int
     */
    private $ttl = 3600;

    /**
     * @var callable
     */
    private $remember;

    /**
     * @var callable
     */
    private $loginProvider;

    /**
     * @var callable
     */
    private $logoutProvider;

    /**
     * @param null $driver
     * @throws Exception
     */
    public function __construct($driver = null)
    {
        if (is_null($driver)) {
            $driver = fmr(sessionKey());
        }

        $this->driver = $driver;

        live($this);
    }

    /**
     * @param string $key
     * @param $value
     *
     * @return Live
     *
     * @throws Exception
     * @throws \Exception
     */
    public function set(string $key, $value): self
    {
        $this->driver->set($key, $value, $this->ttl);

        return $this;
    }

    /**
     * @param null|string $key
     *
     * @return Live
     *
     * @throws Exception
     * @throws \Exception
     */
    public function erase(?string $key = null): self
    {
        if (is_null($key)) {
            $this->destroy();
        } else {
            getEventManager()->fire('live.erase', $this, $key);
            $this->driver->delete($key);
        }

        return $this;
    }

    /**
     * @return Live
     *
     * @throws \ReflectionException
     */
    public function destroy(): self
    {
        getEventManager()->fire('live.destroy', $this);

        $this->driver->flush();

        return $this;
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return call_user_func_array([$this->driver, $name], $arguments);
    }

    /**
     * @param mixed $offset
     *
     * @return bool
     *
     * @throws Exception
     * @throws \Exception
     */
    public function offsetExists($offset)
    {
        return $this->driver->has($offset);
    }

    /**
     * @param mixed $offset
     *
     * @return mixed
     *
     * @throws Exception
     * @throws \Exception
     */
    public function offsetGet($offset)
    {
        return $this->driver->get($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     *
     * @throws Exception
     * @throws \Exception
     */
    public function offsetSet($offset, $value)
    {
        $this->driver->set($offset, $value, $this->ttl);
    }

    /**
     * @param mixed $offset
     *
     * @throws Exception
     * @throws \Exception
     */
    public function offsetUnset($offset)
    {
        $this->driver->delete($offset);
    }

    /**
     * @param $driver
     *
     * @return Live
     */
    public function setDriver($driver): Live
    {
        $this->driver = $driver;

        return $this;
    }

    /**
     * @param int $ttl
     *
     * @return Live
     */
    public function setTtl(int $ttl): Live
    {
        $this->ttl = $ttl;

        return $this;
    }

    /**
     * @param callable $remember
     *
     * @return Live
     */
    public function setRemember(callable $remember): self
    {
        $this->remember = $remember;

        return $this;
    }

    /**
     * @param null|ServerRequestInterface $request
     *
     * @return bool
     *
     * @throws \ReflectionException
     */
    public function remember(?ServerRequestInterface $request = null): bool
    {
        if (is_callable($this->remember)) {
            $request = $request ?: getContainer()->getRequest();

            return callCallable($this->remember, $this, $request);
        }

        return false;
    }

    /**
     * @param callable $provider
     *
     * @return Live
     */
    public function setLoginProvider(callable $loginProvider): self
    {
        $this->loginProvider = $loginProvider;

        return $this;
    }

    /**
     * @param callable $provider
     *
     * @return Live
     */
    public function setLogoutProvider(callable $logoutProvider): self
    {
        $this->logoutProvider = $logoutProvider;

        return $this;
    }

    /**
     * @param null|ServerRequestInterface $request
     *
     * @return bool
     *
     * @throws \ReflectionException
     */
    public function login(?ServerRequestInterface $request = null): bool
    {
        if (is_callable($this->loginProvider)) {
            $request = $request ?: getContainer()->getRequest();

            $status = callCallable($this->loginProvider, $this, $request);

            getEventManager()->fire('live.login', $status, $this, $request);

            return $status;
        }

        return false;
    }

    /**
     * @param null|ServerRequestInterface $request
     *
     * @return bool
     *
     * @throws \ReflectionException
     */
    public function logout(?ServerRequestInterface $request = null): bool
    {
        if (is_callable($this->logoutProvider)) {
            $request = $request ?: getContainer()->getRequest();

            $status = callCallable($this->logoutProvider, $this, $request);

            getEventManager()->fire('live.logout', $status, $this, $request);

            return $status;
        }

        return false;
    }

    /**
     * @param null|string $key
     *
     * @return array|mixed|null
     *
     * @throws \ReflectionException
     */
    public function user(?string $key = null)
    {
        $user = $this['user'];

        if (!is_null($user)) {
            if (!is_null($key)) {
                return dataget($user, $key, null);
            }
        }

        return $user;
    }

    /**
     * @return bool
     *
     * @throws \ReflectionException
     */
    public function isAuth(): bool
    {
        return !is_null($this->user());
    }

    /**
     * @return bool
     *
     * @throws \ReflectionException
     */
    public function isGuest(): bool
    {
        return is_null($this->user());
    }

    /**
     * @throws \ReflectionException
     */
    public function registering()
    {
        getEventManager()->fire('live.registering', $this);
    }
}