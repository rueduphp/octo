<?php
namespace Octo;

use Traversable;

class Ultimatearray implements
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

    protected static $data = [];

    public function __construct(string $namespace = 'web', string $userKey = 'user')
    {
        $this->namespace = $namespace;
        $this->userKey = $userKey;
    }

    /**
     * @param string $key
     * @param null $default
     * @return null
     */
    public function get(string $key, $default = null)
    {
        if ($this->has($key)) {
            return static::$data[$this->makeKey($key)];
        }

        return $default;
    }

    /**
     * @param string $key
     * @param $value
     * @return Ultimatearray
     */
    public function set(string $key, $value): self
    {
        $this->check();
        static::$data[$index = $this->makeKey($key)] = $value;
        static::$data[$index . '._at'] = time();

        return $this;
    }

    /**
     * @param string $key
     * @return int
     */
    public function age(string $key): int
    {
        if ($this->has($key)) {
            $index = $this->makeKey($key);

            return (int) static::$data[$index . '._at'];
        }

        return 0;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key)
    {
        $this->check();

        return notSame('octodummy', isAke(static::$data, $this->makeKey($key), 'octodummy'));
    }

    /**
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        $status = $this->has($key);
        unset(static::$data[$this->makeKey($key)]);

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
        return $this->get('_previous.url', '/');
    }

    /**
     * @param string $url
     * @return Ultimatearray
     */
    public function setPreviousUrl(string $url): self
    {
        return $this->set('_previous.url', $url);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count(Arrays::pattern(static::$data, $this->namespace . '.*'));
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
        return session_id();
    }

    /**
     * @return bool
     */
    public function regenerate()
    {
        return session_regenerate_id();
    }

    /**
     * @return bool
     */
    public function destroy(): bool
    {
        $this->erase();

        return session_destroy();
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
     * @return Ultimate
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
     * @return Ultimate
     */
    public function setUserKey(string $userKey): self
    {
        $this->userKey = $userKey;

        return $this;
    }

    /**
     * @return string
     */
    protected function generateSessionId()
    {
        return sha1(
            uniqid('', true) .
            token() .
            microtime(true)
        );
    }

    /**
     * @param $id
     * @return bool
     */
    public function isValidId($id)
    {
        return is_string($id) && preg_match('/^[a-f0-9]{40}$/', $id);
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
     * @return Ultimatearray
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
     * @return Ultimate
     */
    public function many($data): self
    {
        $data = arrayable($data) ? $data->toArray() : $data;

        return $this->put($data);
    }

    /**
     * @param array $attributes
     * @return Ultimate
     */
    public function replace(array $attributes): self
    {
        $this->erase();

        return $this->put($attributes);
    }

    /**
     * @param array $attributes
     * @return Ultimatearray
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
        $keys = Arrays::pattern(static::$data, $this->namespace . '.*');

        $collection = [];

        foreach ($keys as $key => $value) {
            if (!fnmatch('*._at', $key)) {
                $collection[str_replace($this->namespace . '.', '', $key)] = $value;
            }
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

        foreach (Arrays::pattern(static::$data, $this->namespace . '.*') as $key => $value) {
            unset(static::$data[$key]);
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
        return coll($this->all());
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
     * @return Ultimatearray
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
     * @return Ultimatearray
     */
    public function push(string $key, $value)
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
     * @return Ultimatearray
     */
    public function flash(string $key, $value = true)
    {
        return $this->set($key, $value)->push('_flash.new', $key)->removeFromOldFlashData([$key]);
    }

    /**
     * @param $key
     * @param $value
     */
    public function now($key, $value)
    {
        $this->set($key, $value);

        $this->push('_flash.old', $key);
    }

    /**
     * @return Ultimatearray
     */
    public function reflash(): self
    {
        $this->mergeNewFlashes($this->get('_flash.old', []));

        return $this->set('_flash.old', []);
    }

    /**
     * @param null $keys
     * @return Ultimatearray
     */
    public function keep($keys = null)
    {
        return $this->mergeNewFlashes($keys = is_array($keys) ? $keys : func_get_args())
            ->removeFromOldFlashData($keys);
    }

    /**
     * @param array $keys
     * @return Ultimatearray
     */
    protected function mergeNewFlashes(array $keys)
    {
        $values = array_unique(array_merge($this->get('_flash.new', []), $keys));

        return $this->set('_flash.new', $values);
    }

    /**
     * @param array $keys
     * @return Ultimatearray
     */
    protected function removeFromOldFlashData(array $keys)
    {
        return $this->set('_flash.old', array_diff($this->get('_flash.old', []), $keys));
    }

    /**
     * @param array $value
     */
    public function flashInput(array $value)
    {
        $this->flash('_old_input', $value);
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
     * @return bool|null|Ultimatearray
     */
    public function __call(string $method, array $parameters)
    {
        if (fnmatch('get*', $method)) {
            $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($method, 3)));
            $key                = Strings::lower($uncamelizeMethod);
            $args               = [$key];

            if (!empty($parameters)) {
                $args[] = current($parameters);
            }

            return $this->get(...$args);
        } elseif (fnmatch('set*', $method)) {
            $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($method, 3)));
            $key                = Strings::lower($uncamelizeMethod);

            return $this->set($key, current($parameters));
        } elseif (fnmatch('forget*', $method)) {
            $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($method, 6)));
            $key                = Strings::lower($uncamelizeMethod);

            return $this->delete($key);
        } elseif (fnmatch('has*', $method)) {
            $uncamelizeMethod   = Strings::uncamelize(lcfirst(substr($method, 3)));
            $key                = Strings::lower($uncamelizeMethod);

            return $this->has($key);
        }

        if (!empty($parameters)) {
            return $this->set($method, current($parameters));
        }

        return $this->get($method);
    }

    protected function check(): void
    {
        if (!is_array(static::$data)) {
            static::$data = [];
        }
    }

    /**
     * @param string $key
     * @return string
     */
    protected function makeKey(string $key): string
    {
        return $this->namespace . '.' . $key;
    }

    /**
     * @return Iterator|Traversable
     */
    public function getIterator()
    {
        return new Iterator($this->all());
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * @return Ultimatearray
     */
    public function __clone()
    {
        return (new static($this->namespace . '.clone'))->fill($this->all());
    }
}
