<?php

namespace Octo;

use ArrayAccess;
use Closure;
use SessionHandlerInterface;

class Instant implements ArrayAccess
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * @var \SessionHandlerInterface
     */
    protected $handler;

    /**
     * @var bool
     */
    protected $started = false;

    /**
     * @param string $name
     * @param SessionHandlerInterface $handler
     */
    public function __construct(string $name, SessionHandlerInterface $handler)
    {
        $this->setId(sha1(forever() . $name));
        $this->name = $name;
        $this->handler = $handler;
    }

    public function __destruct()
    {
        if (true === $this->started) {
            $this->save();
            $this->started = false;
        }
    }

    /**
     * @return Instant
     */
    public function start(): self
    {
        if (false === $this->started) {
            $this->loadSession();

            if (!$this->has('_token')) {
                $this->regenerateToken();
            }
        }

        $this->started = true;

        return $this;
    }

    /**
     * @return void
     */
    protected function loadSession()
    {
        $this->attributes = array_merge($this->attributes, $this->readFromHandler());
    }

    /**
     * @return array
     */
    protected function readFromHandler()
    {
        if ($data = $this->handler->read($this->getId())) {
            $data = @unserialize($this->prepareForUnserialize($data));

            if ($data !== false && !is_null($data) && is_array($data)) {
                return $data;
            }
        }

        return [];
    }

    /**
     * @param  string  $data
     * @return string
     */
    protected function prepareForUnserialize($data)
    {
        return $data;
    }

    /**
     * @return bool
     */
    public function save()
    {
        $this->ageFlashData();

        $this->handler->write($this->getId(), $this->prepareForStorage(
            serialize($this->attributes)
        ));

        $this->started = false;
    }

    /**
     * @param  string  $data
     * @return string
     */
    protected function prepareForStorage($data)
    {
        return $data;
    }

    /**
     * @return void
     */
    public function ageFlashData()
    {
        $this->forget($this->get('_flash.old', []));

        $this->put('_flash.old', $this->get('_flash.new', []));

        $this->put('_flash.new', []);
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->attributes;
    }

    /**
     * @param  string  $key
     * @return bool
     */
    public function exists($key)
    {
        return $this->has($key);
    }

    /**
     * @param  string  $key
     * @return bool
     */
    public function has(string $key)
    {
        return 'octodummy' !== $this->get($key, 'octodummy');
    }

    /**
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return aget($this->attributes, $key, $default);
    }

    /**
     * @param  string  $key
     * @param  string  $default
     * @return mixed
     */
    public function pull($key, $default = null)
    {
        return Arrays::pull($this->attributes, $key, $default);
    }

    /**
     * @param  string  $key
     * @return bool
     */
    public function hasOldInput($key = null)
    {
        $old = $this->getOldInput($key);

        return is_null($key) ? count($old) > 0 : ! is_null($old);
    }

    /**
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function getOldInput($key = null, $default = null)
    {
        return aget($this->get('_old_input', []), $key, $default);
    }

    /**
     * @param  array  $attributes
     * @return void
     */
    public function replace(array $attributes)
    {
        $this->put($attributes);
    }

    /**
     * @param $key
     * @param null $value
     * @return Instant
     */
    public function put($key, $value = null): self
    {
        if (! is_array($key)) {
            $key = [$key => $value];
        }

        foreach ($key as $arrayKey => $arrayValue) {
            aset($this->attributes, $arrayKey, $arrayValue);
        }

        return $this;
    }

    /**
     * @param mixed ...$args
     * @return Instant
     */
    public function set(...$args)
    {
        return $this->put(...$args);
    }

    /**
     * @param $key
     * @param Closure $callback
     * @return mixed|Tap
     * @throws \ReflectionException
     */
    public function remember($key, Closure $callback)
    {
        if (!is_null($value = $this->get($key))) {
            return $value;
        }

        return tap($callback(), function ($value) use ($key) {
            $this->put($key, $value);
        });
    }

    /**
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function push($key, $value)
    {
        $array = $this->get($key, []);

        $array[] = $value;

        $this->put($key, $array);
    }

    /**
     * @param  string  $key
     * @param  int  $amount
     * @return mixed
     */
    public function increment($key, $amount = 1)
    {
        $this->put($key, $value = $this->get($key, 0) + $amount);

        return $value;
    }

    /**
     * @param  string  $key
     * @param  int  $amount
     * @return int
     */
    public function decrement($key, $amount = 1)
    {
        return $this->increment($key, $amount * -1);
    }

    /**
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function flash(string $key, $value = true)
    {
        $this->put($key, $value);

        $this->push('_flash.new', $key);

        $this->removeFromOldFlashData([$key]);
    }

    /**
     * @param  string $key
     * @param  mixed $value
     * @return void
     */
    public function now($key, $value)
    {
        $this->put($key, $value);

        $this->push('_flash.old', $key);
    }

    /**
     * @return void
     */
    public function reflash()
    {
        $this->mergeNewFlashes($this->get('_flash.old', []));

        $this->put('_flash.old', []);
    }

    /**
     * @param  array|mixed  $keys
     * @return void
     */
    public function keep($keys = null)
    {
        $this->mergeNewFlashes($keys = is_array($keys) ? $keys : func_get_args());

        $this->removeFromOldFlashData($keys);
    }

    /**
     * @param  array  $keys
     * @return void
     */
    protected function mergeNewFlashes(array $keys)
    {
        $values = array_unique(array_merge($this->get('_flash.new', []), $keys));

        $this->put('_flash.new', $values);
    }

    /**
     * @param  array  $keys
     * @return void
     */
    protected function removeFromOldFlashData(array $keys)
    {
        $this->put('_flash.old', array_diff($this->get('_flash.old', []), $keys));
    }

    /**
     * @param  array  $value
     * @return void
     */
    public function flashInput(array $value)
    {
        $this->flash('_old_input', $value);
    }

    /**
     * @param  string  $key
     * @return mixed
     */
    public function remove($key)
    {
        return Arrays::pull($this->attributes, $key);
    }

    /**
     * @param  string|array  $keys
     * @return void
     */
    public function forget($keys)
    {
        Arrays::forget($this->attributes, $keys);
    }

    /**
     * @param  string|array  $keys
     * @return void
     */
    public function delete($keys)
    {
        Arrays::forget($this->attributes, $keys);
    }

    /**
     * @param  string|array  $keys
     * @return void
     */
    public function del($keys)
    {
        Arrays::forget($this->attributes, $keys);
    }

    /**
     * @return void
     */
    public function flush()
    {
        $this->attributes = [];
    }

    /**
     * @return bool
     */
    public function invalidate()
    {
        $this->flush();

        return $this->migrate(true);
    }

    /**
     * @param  bool  $destroy
     * @return bool
     */
    public function regenerate($destroy = false)
    {
        return $this->migrate($destroy);
    }

    /**
     * @param  bool  $destroy
     * @return bool
     */
    public function migrate($destroy = false)
    {
        if ($destroy) {
            $this->handler->destroy($this->getId());
        }

        $this->setExists(false);

        $this->setId($this->generateSessionId());

        return true;
    }

    /**
     * @return bool
     */
    public function isStarted()
    {
        return $this->started;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param  string  $name
     * @return void
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param  string  $id
     * @return void
     */
    public function setId($id)
    {
        $this->id = $this->isValidId($id) ? $id : $this->generateSessionId();
    }

    /**
     * @param  string  $id
     * @return bool
     */
    public function isValidId($id)
    {
        return is_string($id) && ctype_alnum($id) && 40 === strlen($id);
    }

    /**
     * @return string
     */
    protected function generateSessionId()
    {
        return Inflector::random(40);
    }

    /**
     * @param  bool  $value
     * @return void
     */
    public function setExists($value)
    {
        if (true === hasMethod($this->handler, 'setExists')) {
            $this->handler->setExists($value);
        }
    }

    /**
     * @return string
     */
    public function token()
    {
        return $this->get('_token');
    }

    /**
     * @return void
     */
    public function regenerateToken()
    {
        $this->put('_token', token());
    }

    /**
     * @return string|null
     */
    public function previousUrl()
    {
        return $this->get('_previous.url');
    }

    /**
     * @param  string  $url
     * @return void
     */
    public function setPreviousUrl($url)
    {
        $this->put('_previous.url', $url);
    }

    /**
     * @return \SessionHandlerInterface
     */
    public function getHandler()
    {
        return $this->handler;
    }

    /**
     * @return bool
     */
    public function handlerNeedsRequest()
    {
        return hasMethod($this->handler, 'setRequest');
    }

    /**
     * @param  $request
     * @return void
     */
    public function setRequestOnHandler($request)
    {
        if (true === $this->handlerNeedsRequest()) {
            $this->handler->setRequest($request);
        }
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }
}
