<?php
namespace App\Services;

use Octo\Collection;
use Octo\Facades\Config as CoreConf;
use Octo\FastObject;
use Octo\FastSessionInterface;
use Octo\Inflector;
use Octo\Live;
use Traversable;

class Reddy implements
    FastSessionInterface,
    \ArrayAccess,
    \Countable,
    \IteratorAggregate {
    /**
     * @var string
     */
    protected $namespace;

    /**
     * @var string
     */
    protected $userKey;

    /**
     * @var string
     */
    protected $userModel;

    /**
     * @var array
     */
    protected $config;

    public function __construct(
        string $namespace = 'web',
        string $userKey = 'user',
        string $userModel = '\\App\\Models\\User'
    )  {
        $this->namespace    = $namespace;
        $this->userKey      = $userKey;
        $this->userModel    = $userModel;
        $this->config       = CoreConf::get('session', []);
    }

    /**
     * @param string $key
     * @param null $default
     * @return null
     */
    public function get(string $key, $default = null)
    {
        if ($this->has($key)) {
            return $this->read($key, $default);
        }

        return $default;
    }

    /**
     * @param string $key
     * @param $value
     * @return Reddy
     */
    public function set(string $key, $value): self
    {
        $this->write($key, $value);

        return $this;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->available($key);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        $status = $this->has($key);
        $this->unwrite($key);

        return $status;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function forget(string $key): bool
    {
        return $this->delete($key);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function remove(string $key): bool
    {
        return $this->delete($key);
    }

    /**
     * @return string
     */
    public function previousUrl(): string
    {
        return $this->get('previous.url', '/');
    }

    /**
     * @param string $url
     * @param null|FastObject $route
     * @return Reddy
     */
    public function setPreviousUrl(string $url, ?FastObject $route = null): self
    {
        $routeName = $route ? $route->name : null;

        return $this
            ->set('previous.url', $this->get('_previous.url', '/'))
            ->set('previous.route', $this->get('_previous.route', 'home'))
            ->set('_previous.url', $url)
            ->set('_previous.route', $routeName);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        $keys = $this->store()->keys(\Octo\forever() . '.' . $this->namespace . '.*');

        return count($keys);
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return 0 === $this->count();
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function pull(string $key, $default = null)
    {
        $value = $this->get($key, $default);

        $this->forget($key);

        return $value;
    }

    /**
     * @return bool
     */
    public function drop(): bool
    {
        return $this->erase();
    }

    /**
     * @return string
     */
    public function getSessionId()
    {
        return\Octo\forever();
    }

    /**
     * @return bool
     */
    public function destroy(): bool
    {
        $this->erase();
    }

    /**
     * @param null|string $key
     * @param null $default
     * @return mixed|null
     */
    public function user(?string $key = null, $default = null)
    {
        $user = $this->get($this->userKey);

        if (null !== $user) {
            return null !== $key ? isAke($user, $key, $default) : $user;
        }

        return $default;
    }

    /**
     * @return bool
     */
    public function guest(): bool
    {
        return null === $this->user();
    }

    /**
     * @return bool
     */
    public function logged(): bool
    {
        return null !== $this->user();
    }

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @param string $namespace
     * @return Reddy
     */
    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * @return string
     */
    public function getUserKey(): string
    {
        return $this->userKey;
    }

    /**
     * @param string $userKey
     * @return Reddy
     */
    public function setUserKey(string $userKey): self
    {
        $this->userKey = $userKey;

        return $this;
    }

    /**
     * @return string
     */
    public function getUserModel(): string
    {
        return $this->userModel;
    }

    /**
     * @param string $userModel
     * @return Reddy
     */
    public function setUserModel(string $userModel): self
    {
        $this->userModel = $userModel;

        return $this;
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     */
    public function increment(string $key, int $by = 1)
    {
        $this->set($key, $value = $this->get($key, 0) + $by);

        return $value;
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     */
    public function decrement(string $key, int $by = 1)
    {
        return $this->increment($key, $by * -1);
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     */
    public function incr(string $key, int $by = 1)
    {
        return $this->increment($key, $by);
    }

    /**
     * @param string $key
     * @param int $by
     * @return int
     */
    public function decr(string $key, int $by = 1)
    {
        return $this->increment($key, $by * -1);
    }

    /**
     * @param $key
     * @param null $value
     * @return Reddy
     */
    public function put($key, $value = null): self
    {
        if (!is_array($key) && null === $value) {
            $key = [$key => $value];
        }

        foreach ($key as $arrayKey => $arrayValue) {
            $this->set($arrayKey, $arrayValue);
        }

        return $this;
    }

    /**
     * @param $data
     * @return Reddy
     */
    public function many($data): self
    {
        $data = \Octo\arrayable($data) ? $data->toArray() : $data;

        return $this->put($data);
    }

    /**
     * @param array $attributes
     * @return Reddy
     */
    public function replace(array $attributes): self
    {
        $this->erase();

        return $this->put($attributes);
    }

    /**
     * @param array $attributes
     * @return Reddy
     */
    public function merge(array $attributes): self
    {
        return $this->put($attributes);
    }

    /**
     * @return array
     */
    public function all(): array
    {
        $keys = $this->store()->keys(\Octo\forever() . '.' . $this->namespace . '.*');

        $collection = [];

        foreach ($keys as $key => $value) {
            $collection[str_replace(\Octo\forever() . '.' . $this->namespace . '.', '', $key)] = $value;
        }

        return $collection;
    }

    /**
     * @param null|string $row
     * @return bool
     */
    public function erase(?string $row = null): bool
    {
        if (null !== $row) {
            return $this->delete($row);
        }

        $keys = $this->store()->keys(\Octo\forever() . '.' . $this->namespace . '.*');

        foreach ($keys as $key) {
            $this->store()->del($key);
        }

        return 0 === $this->toCollection()->count();
    }

    /**
     * @return bool
     */
    public function flush(): bool
    {
        return $this->erase();
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->all();
    }

    /**
     * @return Collection
     */
    public function toCollection(): Collection
    {
        return \Octo\coll($this->all());
    }

    /**
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->all(), JSON_PRETTY_PRINT);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function __isset(string $key)
    {
        return $this->has($key);
    }

    /**
     * @param string $key
     */
    public function __unset(string $key)
    {
        $this->delete($key);
    }

    /**
     * @param string $key
     * @param $value
     */
    public function __set(string $key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * @param string $key
     * @return null
     */
    public function __get(string $key)
    {
        return $this->get($key);
    }

    /**
     * @param array $rows
     * @return Reddy
     */
    public function fill(array $rows = []): self
    {
        foreach ($rows as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * @param string $key
     * @param $value
     * @return Reddy
     */
    public function push(string $key, $value): self
    {
        $array = $this->get($key, []);

        $array[] = $value;

        return $this->set($key, $array);
    }

    /**
     * @param string $key
     * @return mixed
     */
    function pushDown(string $key)
    {
        return $this->pull($key);
    }

    /**
     * @param string $key
     * @param bool $value
     * @return Reddy
     */
    public function flash(string $key, $value = true): self
    {
        return $this->set($key, $value)->push('_flash.new', $key)->removeFromOldFlashData([$key]);
    }

    /**
     * @param $key
     * @param $value
     * @return Reddy
     */
    public function now($key, $value): self
    {
        return $this->set($key, $value)->push('_flash.old', $key);
    }

    /**
     * @return Reddy
     */
    public function reflash(): self
    {
        return $this->mergeNewFlashes($this->get('_flash.old', []))
            ->set('_flash.old', []);
    }

    /**
     * @param null $keys
     * @return Reddy
     */
    public function keep($keys = null)
    {
        return $this->mergeNewFlashes($keys = is_array($keys) ? $keys : func_get_args())
            ->removeFromOldFlashData($keys);
    }

    /**
     * @param array $keys
     * @return Reddy
     */
    protected function mergeNewFlashes(array $keys): self
    {
        $values = array_unique(array_merge($this->get('_flash.new', []), $keys));

        return $this->set('_flash.new', $values);
    }

    /**
     * @param array $keys
     * @return Reddy
     */
    protected function removeFromOldFlashData(array $keys): self
    {
        return $this->set('_flash.old', array_diff($this->get('_flash.old', []), $keys));
    }

    /**
     * @param array $value
     * @return Reddy
     */
    public function flashInput(array $value)
    {
        return $this->flash('_old_input', $value);
    }

    /**
     * @param $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * @param $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @param $offset
     * @param $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * @param $offset
     */
    public function offsetUnset($offset)
    {
        $this->delete($offset);
    }

    /**
     * @param string $method
     * @param array $parameters
     * @return Reddy|bool|null
     */
    public function __call(string $method, array $parameters)
    {
        if (fnmatch('get*', $method)) {
            $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($method, 3)));
            $key                = Inflector::lower($uncamelizeMethod);
            $args               = [$key];

            if (!empty($parameters)) {
                $args[] = current($parameters);
            }

            return $this->get(...$args);
        } elseif (fnmatch('set*', $method)) {
            $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($method, 3)));
            $key                = Inflector::lower($uncamelizeMethod);

            return $this->set($key, current($parameters));
        } elseif (fnmatch('forget*', $method)) {
            $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($method, 6)));
            $key                = Inflector::lower($uncamelizeMethod);

            return $this->delete($key);
        } elseif (fnmatch('has*', $method)) {
            $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($method, 3)));
            $key                = Inflector::lower($uncamelizeMethod);

            return $this->has($key);
        }

        if (!empty($parameters)) {
            return $this->set($method, current($parameters));
        }

        return $this->get($method);
    }

    /**
     * @return bool
     */
    public function alive()
    {
        return true;
    }

    /**
     * @param string $key
     * @return string
     */
    protected function makeKey(string $key): string
    {
        return \Octo\forever() . '.' . $this->namespace . '.' . $key;
    }

    /**
     * @return \Octo\Iterator|Traversable
     */
    public function getIterator()
    {
        return new \Octo\Iterator($this->all());
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * @return Reddy
     */
    public function __clone()
    {
        return (new static($this->namespace . '.clone'))->fill($this->all());
    }

    /**
     * @return Live
     * @throws \ReflectionException
     */
    public function guard(): Live
    {
        return \Octo\live();
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function token()
    {
        return csrf();
    }

    /**
     * @param null|string $key
     * @param null $default
     * @return mixed|null
     */
    public function getOldInput(?string $key = null, $default = null)
    {
        return \Octo\aget($this->get('_old_input', []), $key, $default);
    }

    /**
     * @param string $key
     * @param callable $c
     * @return mixed|null
     * @throws \ReflectionException
     */
    public function getOr(string $key, callable $c)
    {
        if (!$this->has($key)) {
            $value = \Octo\callThat($c);

            $this->set($key, $value);

            return $value;
        }

        return $this->get($key);
    }

    /**
     * @param string $key
     * @param $value
     */
    protected function write(string $key, $value)
    {
        $this->store()->set(
            $k = $this->makeKey($key),
            serialize($value)
        );

        $this->store()->expire($k, $this->config['ttl'] ?? 3600);
    }

    public function ttl(string $key)
    {
        return $this->store()->ttl($this->makeKey($key));
    }

    /**
     * @param string $key
     * @param null $default
     * @return null|string
     */
    protected function read(string $key, $default = null)
    {
        $value = $this->store()->get($this->makeKey($key));

        if ($value) {
            return unserialize($value);
        }

        return $default;
    }

    /**
     * @param string $key
     * @return bool
     */
    protected function unwrite(string $key): bool
    {
        return $this->store()->del($this->makeKey($key)) > 0;
    }

    /**
     * @param string $key
     * @return bool
     */
    protected function available(string $key): bool
    {
        return $this->store()->exists($this->makeKey($key)) > 0;
    }

    /**
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function command(string $method, array $parameters = [])
    {
        return $this->store()->{$method}(...$parameters);
    }

    /**
     * @return \Illuminate\Redis\RedisManager
     */
    protected function store()
    {
        return redis();
    }
}
