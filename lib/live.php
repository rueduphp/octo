<?php
namespace Octo;

use ArrayAccess;
use Psr\Http\Message\ServerRequestInterface;

class Live implements ArrayAccess, FastSessionInterface
{
    /**
     * @var FastCacheInterface
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
     * @var string
     */
    private $sid;

    /**
     * @param null|FastCacheInterface $driver
     * @throws Exception
     */
    public function __construct($driver = null)
    {
        $this->sid = sessionKey();

        if (is_null($driver)) {
            $driver = fmr($this->sid);
        }

        $this->driver = $driver;

        live($this);
    }

    /**
     * @return string
     */
    public function sid(): string
    {
        return $this->sid;
    }

    /**
     * @return bool
     */
    public function open(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function close(): bool
    {
        $this->sid = null;

        return true;
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

    /**
     * @param string $key
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function __isset(string $key)
    {
        return $this->driver->has($key);
    }

    /**
     * @param string $key
     * @throws Exception
     * @throws \Exception
     */
    public function __unset(string $key)
    {
        $this->driver->delete($key);
    }

    /**
     * @param string $key
     * @param $value
     * @throws Exception
     * @throws \Exception
     */
    public function __set(string $key, $value)
    {
        $this->driver->set($key, $value, $this->ttl);
    }

    /**
     * @param string $key
     * @return mixed
     * @throws Exception
     * @throws \Exception
     */
    public function __get(string $key)
    {
        return $this->driver->get($key);
    }

    /**
     * @return array
     * @throws Exception
     * @throws \Exception
     */
    public function all()
    {
        return $this->driver->all();
    }

    /**
     * @param array $rows
     * @return Live
     * @throws Exception
     * @throws \Exception
     */
    public function fill(array $rows = []): self
    {
        foreach ($rows as $key => $value) {
            $this->driver->set($key, $value);
        }

        return $this;
    }

    /**
     * @param string $key
     * @param $value
     * @throws Exception
     */
    public function push(string $key, $value)
    {
        $array = $this->driver->get($key, []);

        $array[] = $value;

        $this->set($key, $array);
    }

    /**
     * @param string $key
     * @param bool $value
     * @throws Exception
     */
    public function flash(string $key, $value = true)
    {
        $this->set($key, $value);

        $this->push('_flash.new', $key);

        $this->removeFromOldFlashData([$key]);
    }

    /**
     * @param $key
     * @param $value
     * @throws Exception
     */
    public function now($key, $value)
    {
        $this->set($key, $value);

        $this->push('_flash.old', $key);
    }

    /**
     * @throws Exception
     */
    public function reflash()
    {
        $this->mergeNewFlashes($this->driver->get('_flash.old', []));

        $this->set('_flash.old', []);
    }

    /**
     * @param null $keys
     * @throws Exception
     */
    public function keep($keys = null)
    {
        $this->mergeNewFlashes($keys = is_array($keys) ? $keys : func_get_args());

        $this->removeFromOldFlashData($keys);
    }

    /**
     * @param array $keys
     * @throws Exception
     */
    protected function mergeNewFlashes(array $keys)
    {
        $values = array_unique(array_merge($this->driver->get('_flash.new', []), $keys));

        $this->set('_flash.new', $values);
    }

    /**
     * @param array $keys
     * @throws Exception
     */
    protected function removeFromOldFlashData(array $keys)
    {
        $this->set('_flash.old', array_diff($this->driver->get('_flash.old', []), $keys));
    }

    /**
     * @param array $value
     * @throws Exception
     */
    public function flashInput(array $value)
    {
        $this->flash('_old_input', $value);
    }
}