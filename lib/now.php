<?php
namespace Octo;

use ArrayAccess as AA;

class Now implements AA
{
    private static $data = [];
    private $ns;

    public function __construct($ns = 'core', $data = [])
    {
        $data = arrayable($data) ? $data->toArray() : $data;

        $this->ns = $ns;

        if (!isset(self::$data[$ns])) {
            self::$data[$ns] = [];
        }

        foreach ($data as $k => $v) {
            self::$data[$ns][$k] = $v;
        }
    }

    /**
     * @param string $ns
     * @return Now
     */
    public function setNS(string $ns): self
    {
        $this->ns = $ns;

        if (!isset(self::$data[$ns])) {
            self::$data[$ns] = [];
        }

        return $this;
    }

    public function reset($ns = null)
    {
        $ns = is_null($ns) ? $this->ns : $ns;

        unset(self::$data[$ns]);

        return $this;
    }

    public function truncate()
    {
        return $this->reset();
    }

    public function resetAll()
    {
        self::$data = [];

        return $this;
    }

    public function drop()
    {
        self::$data = [];

        return $this;
    }

    public function getDirectory()
    {
        return 'now.' . $this->ns;
    }

    public function copy($new, $drop = false)
    {
        if (!isset(self::$data[$new])) {
            $actualData = self::$data[$this->ns];

            if ($drop) {
                unset(self::$data[$this->ns]);
            }

            $this->ns = $new;

            self::$data[$this->ns] = $actualData;

            return $this;
        } else {
            exception('now', "Yhe namespace $new is already in use.");
        }
    }

    public function rename($new)
    {
        return $this->copy($new, true);
    }

    /**
     * @param string $pattern
     *
     * @return array
     */
    public function pattern($pattern = '*')
    {
        return Arrays::pattern(self::$data[$this->ns], $pattern);
    }

    /**
     * @return Now
     */
    public function flush(): self
    {
        self::$data[$this->ns] = [];

        return $this;
    }

    /**
     * @param array $rows
     * @return Now
     */
    public function fill(array $rows = []): self
    {
        $data = self::$data[$this->ns];

        $data = array_merge($data, $rows);

        self::$data[$this->ns] = $data;

        return $this;
    }

    public function __set($k, $v)
    {
        self::$data[$this->ns][$k] = $v;

        return $this;
    }

    public function __get($k)
    {
        if (isset(self::$data[$this->ns][$k])) {
            return self::$data[$this->ns][$k];
        }

        return null;
    }

    public function __isset($k)
    {
        return isset(self::$data[$this->ns][$k]);
    }

    public function __unset($k)
    {
        unset(self::$data[$this->ns][$k]);

        return $this;
    }

    /**
     * @param string $k
     * @param $v
     *
     * @return Now
     */
    public function set(string $k, $v): self
    {
        self::$data[$this->ns][$k] = $v;

        return $this;
    }

    public function add(string $k, $v)
    {
        return $this->set($k, $v);
    }

    public function put(string $k, $v)
    {
        return $this->set($k, $v);
    }

    public function flash(string $k, $v = null)
    {
        $k = 'flash.' . $k;

        if (is_null($v)) {
            return $this->get($k);
        }

        return $this->set($k, $v);
    }

    public function offsetSet($k, $v)
    {
        self::$data[$this->ns][$k] = $v;

        return $this;
    }

    public function get(string $k, $default = null)
    {
        if (isset(self::$data[$this->ns][$k])) {
            return self::$data[$this->ns][$k];
        }

        return $default;
    }

    /**
     * @param $k
     * @param array $default
     * @return \Generator
     */
    public function collection(string $k, $default = [])
    {
        $data = !isset(self::$data[$this->ns][$k]) ? $default : self::$data[$this->ns][$k];

        foreach ($data as $row) {
            yield $row;
        }
    }

    public function getOr(string $k, callable $c)
    {
        $res = $this->get($k, 'octodummy');

        if ('octodummy' === $res) {
            $this->set($k, $res = $c());
        }

        return $res;
    }

    public function listen(string $k, callable $c)
    {
        $k = "event.$k";
        self::$data[$this->ns][$k] = $c;

        return $this;
    }

    public function fire(string $k, array $args = [], $d = null)
    {
        $k = "event.$k";
        $c = isAke(self::$data[$this->ns], $k, $d);

        if (is_callable($c)) {
            return call_user_func_array($c, $args);
        }

        return $d;
    }

    public function offsetGet($k)
    {
        if ($this->has($k)) {
            return $this->get($k);
        }

        return null;
    }

    public function has(string $k)
    {
        return isset(self::$data[$this->ns][$k]);
    }

    public function exists(string $k)
    {
        return isset(self::$data[$this->ns][$k]);
    }

    public function offsetExists($k)
    {
        return $this->has($k);
    }

    public function offsetUnset($k)
    {
        return $this->delete($k);
    }

    public function delete(string $k)
    {
        $status = self::has($k);

        unset(self::$data[$this->ns][$k]);

        return $status;
    }

    public function forget(string $k)
    {
        return $this->delete($k);
    }

    public function remove(string $k)
    {
        return $this->delete($k);
    }

    public function del(string $k)
    {
        return $this->delete($k);
    }

    public function erase(string $k)
    {
        return $this->delete($k);
    }

    public function __call(string $m, $a)
    {
        if (fnmatch('get*', $m)) {
            $key = Inflector::uncamelize(substr($m, 3));
            $default = empty($a) ? null : current($a);

            return $this->get($key, $default);
        } elseif (fnmatch('set*', $m)) {
            $key = Inflector::uncamelize(substr($m, 3));

            return $this->set($key, current($a));

        } elseif (fnmatch('has*', $m)) {
            $key = Inflector::uncamelize(substr($m, 3));

            return $this->has($key);

        } elseif (fnmatch('del*', $m)) {
            $key = Inflector::uncamelize(substr($m, 3));

            return $this->delete($key);
        } else {
            $closure = $this->get($m);

            if (is_string($closure) && fnmatch('*::*', $closure)) {
                list($c, $f) = explode('::', $closure, 2);

                try {
                    return call_user_func_array([app($c), $f], $a);
                } catch (\Exception $e) {
                    $default = empty($a) ? null : current($a);

                    return empty($closure) ? $default : $closure;
                }
            } else {
                if (is_callable($closure)) {
                    return call_user_func_array($closure, $a);
                }

                if (!empty($a) && empty($closure)) {
                    if (count($a) === 1) {
                        return $this->set($m, current($a));
                    }
                }

                $default = empty($a) ? null : current($a);

                return empty($closure) ? $default : $closure;
            }
        }
    }

    public static function getInstance(string $ns = 'core', array $data = [])
    {
        if (!isset(self::$data[$ns])) {
            self::$data[$ns] = new self($ns, $data);
        }

        return self::$data[$ns];
    }

    public function incr(string $name, $by = 1)
    {
        $old = $this->get($name, 0);
        $new = $old + $by;

        $this->set($name, $new);

        return (int) $new;
    }

    public function decr(string $name, $by = 1)
    {
        $old = $this->get($name, 1);
        $new = $old - $by;

        $this->set($name, $new);

        return (int) $new;
    }

    public function increment(string $name, $by = 1)
    {
        $old = $this->get($name, 0);
        $new = $old + $by;

        $this->set($name, $new);

        return (int) $new;
    }

    public function decrement(string $name, $by = 1)
    {
        $old = $this->get($name, 1);
        $new = $old - $by;

        $this->set($name, $new);

        return (int) $new;
    }

    public function in(string $name, $data)
    {
        $key = 'tuples.' . $name;
        $check = sha1(serialize($data));

        $tab = $this->get($key, []);

        if (!in_array($check, $tab)) {
            $tab[] = $check;

            $this->set($key, $tab);

            return false;
        }

        return true;
    }

    public function hset($h, $k, $v)
    {
        return $this->set("hash.$h.$k", $v);
    }

    public function hget($h, $k, $d)
    {
        return $this->get("hash.$h.$k", $d);
    }

    public function hgetOr($h, $k, callable $c)
    {
        return $this->getOr("hash.$h.$k", $c);
    }

    public function hhas($h, $k, $d)
    {
        return $this->has("hash.$h.$k");
    }

    public function hdel($h, $k, $d)
    {
        return $this->delete("hash.$h.$k");
    }

    public function hincr($hash, $key, $by = 1)
    {
        $old = $this->hget($hash, $key, 1);
        $new = $old + $by;

        $this->hset($hash, $key, $new);

        return $new;
    }

    public function hdecr($hash, $key, $by = 1)
    {
        $old = $this->hget($hash, $key, 1);
        $new = $old - $by;

        $this->hset($hash, $key, $new);

        return $new;
    }

    /**
     * @param string $pattern
     * @return \Generator
     */
    public function keys(string $pattern = '*')
    {
        $data = self::$data[$this->ns];

        foreach ($data as $k => $v) {
            if (fnmatch($pattern, $k)) {
                yield $k;
            }
        }
    }

    public function hgetall($hash)
    {
        $data = self::$data[$this->ns];

        foreach ($data as $k => $v) {
            if (fnmatch("hash.$hash.*", $k)) {
                yield $k;
                yield $v;
            }
        }
    }

    public function toArray()
    {
        $data = self::$data[$this->ns];
        $keys = array_keys($data);

        $collection = [];

        foreach ($keys as $key) {
            Arrays::set($collection, $key, $data[$key]);
        }

        return $collection;
    }

    public function count()
    {
        return count(self::$data[$this->ns]);
    }

    public function empty()
    {
        return count(self::$data[$this->ns]) == 0;
    }

    public function notEmpty()
    {
        return count(self::$data[$this->ns]) > 0;
    }

    public function toCollection()
    {
        return coll($this->toArray());
    }

    public function toJson($option = JSON_PRETTY_PRINT)
    {
        return json_encode($this->toArray(), $option);
    }

    public function toSerialize()
    {
        return serialize($this->toArray());
    }

    public function getDel($k, $d = null)
    {
        $value = $this->get($k, $d);

        $this->del($k);

        return $value;
    }

    public function pull($k, $d = null)
    {
        $value = $this->get($k, $d);

        $this->del($k);

        return $value;
    }

    public function bind($k, callable $c)
    {
        return $this->set('binds.' . $k, $c);
    }

    public function instance($object)
    {
        if (is_callable($object)) {
            $object = $object();
        }

        $class = get_class($object);

        $resolver = function () use ($object) {
            return $object;
        };

        return $this->bind($class, $resolver);
    }

    /**
     * @param $k
     * @param array $args
     * @param bool $singleton
     *
     * @return mixed|object
     *
     * @throws \ReflectionException
     */
    public function make($k, array $args = [], $singleton = true)
    {
        $c = $this->get('binds.' . $k, null);

        if ($c && is_callable($c) && $singleton) {
            return call_user_func_array($c, $args);
        } else {
            $ref = new \ReflectionClass($k);
            $canMakeInstance = $ref->isInstantiable();

            if ($canMakeInstance) {
                $maker = $ref->getConstructor();

                if ($maker) {
                    if (empty($args)) {
                        $params = $maker->getParameters();

                        $instanceParams = [];

                        foreach ($params as $param) {
                            $classParam = $param->getClass();

                            if ($classParam) {
                                $p = $this->make($classParam->getName());
                            } else {
                                $p = $param->getDefaultValue();
                            }

                            $instanceParams[] = $p;
                        }

                        if (!empty($instanceParams)) {
                            $this->instance($i = $ref->newInstanceArgs($instanceParams));
                        } else {
                            $this->instance($i = $ref->newInstance());
                        }
                    } else {
                        $this->instance($i = $ref->newInstanceArgs($args));
                    }

                    return $i;
                } else {
                    $this->instance($i = $ref->newInstance());

                    return $i;
                }
            } else {
                exception('Dic', "The class $k is not intantiable.");
            }
        }

        exception('Dic', "The class $k is not set.");
    }

    public function core($k, $v = null)
    {
        $rows = $this->get('core.' . $k, []);

        if ($v) {
            $rows[] = $v;
            $this->set('core.' . $k, $rows);

            return $this;
        } else {
            return $rows;
        }
    }

    public function until($k, callable $c, $maxAge = null, $args = [])
    {
        $keyAge = $k . '.maxage';
        $v      = $this->get($k);

        if ($v) {
            if (is_null($maxAge)) {
                return $v;
            }

            $age = $this->get($keyAge);

            if (!$age) {
                $age = $maxAge - 1;
            }

            if ($age >= $maxAge) {
                return $v;
            } else {
                $this->delete($k);
                $this->delete($keyAge);
            }
        }

        $data = call_user_func_array($c, $args);

        $this->set($k, $data);

        if (!is_null($maxAge)) {
            if ($maxAge < 1000000) {
                $maxAge = ($maxAge * 60) + microtime(true);
            }

            $this->set($keyAge, $maxAge);
        }

        return $data;
    }

    /**
     * @param string $key
     * @param $value
     *
     * @return Now
     */
    public function append(string $key, $value)
    {
        $array = $this->get($key, []);

        $array[] = $value;

        return $this->set($key, $array);
    }

    public function all(): array
    {
        $collection = [];

        foreach($this->keys() as $key) {
            $collection[$key] = $this->get($key);
        }

        return $collection;
    }
}
