<?php

namespace Octo;

use Closure;
use function get_class;
use function get_class_methods;
use stdClass;
use SessionHandlerInterface;

class Instant
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
     * @param null|string $id
     */
    public function __construct(string $name, SessionHandlerInterface $handler, ?string $id = null)
    {
        $this->setId($id);
        $this->name = $name;
        $this->handler = $handler;
    }

    /**
     * @return bool
     */
    public function start()
    {
        $this->loadSession();

        if (! $this->has('_token')) {
            $this->regenerateToken();
        }

        return $this->started = true;
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

            if ($data !== false && ! is_null($data) && is_array($data)) {
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
     * @param  string|array  $key
     * @return bool
     */
    public function exists($key)
    {
        $placeholder = new stdClass();

        return !coll(is_array($key) ? $key : func_get_args())->contains(function ($key) use ($placeholder) {
            return $this->get($key, $placeholder) === $placeholder;
        });
    }

    /**
     * @param  string|array  $key
     * @return bool
     */
    public function has($key)
    {
        return !coll(is_array($key) ? $key : func_get_args())->contains(function ($key) {
            return is_null($this->get($key));
        });
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
     * @param  string|array  $key
     * @param  mixed       $value
     * @return void
     */
    public function put($key, $value = null)
    {
        if (! is_array($key)) {
            $key = [$key => $value];
        }

        foreach ($key as $arrayKey => $arrayValue) {
            aset($this->attributes, $arrayKey, $arrayValue);
        }
    }

    /**
     * @param  string  $key
     * @param  \Closure  $callback
     * @return mixed
     */
    public function remember($key, Closure $callback)
    {
        if (! is_null($value = $this->get($key))) {
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
        return is_string($id) && ctype_alnum($id) && strlen($id) === 40;
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
        if (in_array('setExists', get_class_methods($this->handler))) {
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
        return in_array('setRequest', get_class_methods($this->handler));
    }

    /**
     * @param  $request
     * @return void
     */
    public function setRequestOnHandler($request)
    {
        if ($this->handlerNeedsRequest()) {
            $this->handler->setRequest($request);
        }
    }
}
