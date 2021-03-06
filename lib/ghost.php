<?php
namespace Octo;

use ArrayAccess;
use Closure;

class Ghost implements ArrayAccess
{
    use Notifiable;

    private $_instance;

    public function __construct(array $data = [], $instance = null)
    {
        $instance = !$instance ? sha1(uuid() . uuid()) : $instance;
        $this->_instance = $instance;

        foreach ($data as $k => $v) {
            $this->{$k} = $v;
        }
    }

    public function rowing()
    {
        $collection = [];
        $rows = Registry::pattern('ghost.*.' . $this->_instance);

        foreach ($rows as $k => $v) {
            if (!fnmatch('*__macros*', $k)) {
                $key = str_replace(['ghost.', '.' . $this->_instance], '', $k);
                $collection[$key] = $v;
            }
        }

        return $collection;
    }

    public function toArray()
    {
        return $this->rowing();
    }

    public function __toString()
    {
        return json_encode($this->rowing());
    }

    public function __get($k)
    {
        $key = 'ghost.' . $k . '.' . $this->_instance;

        return Registry::get($key, null);
    }

    public function __set($k, $v)
    {
        $key = 'ghost.' . $k . '.' . $this->_instance;

        Registry::set($key, $v);
    }

    public function __isset($k)
    {
        $key = 'ghost.' . $k . '.' . $this->_instance;
        $val = Registry::get($key, 'octodummy');

        return $val !== 'octodummy';
    }

    public function __unset($k)
    {
        $key = 'ghost.' . $k . '.' . $this->_instance;

        Registry::delete($key);
    }

    public function offsetSet($k, $v)
    {
        $this->{$k} = $v;

        return $this;
    }

    public function offsetGet($k)
    {
        return $this->{$k};
    }

    public function offsetExists($k)
    {
        return isset($this->{$k});
    }

    public function offsetUnset($k)
    {
        unset($this->{$k});
    }

    public function resetMacros(): self
    {
        $key = 'ghost.__macros.' . $this->_instance;
        Registry::set($key, []);

        return $this;
    }

    /**
     * @param string $m
     * @param array $a
     *
     * @return $this|bool|mixed|null
     *
     * @throws \ReflectionException
     */
    public function __call(string $m, array $a)
    {
        if (fnmatch('get*', $m) && strlen($m) > 3) {
            $key = Inflector::uncamelize(substr($m, 3));
            $default = empty($a) ? null : current($a);

            return isset($this->{$key}) ? $this->{$key} : $default;
        } elseif (fnmatch('set*', $m) && strlen($m) > 3) {
            $key = Inflector::uncamelize(substr($m, 3));

            $this->{$key} = current($a);

            return $this;
        } elseif (fnmatch('has*', $m) && strlen($m) > 3) {
            $key = Inflector::uncamelize(substr($m, 3));

            return isset($this->{$key});
        } else {
            if (isset($this->{$m})) {
                $val = current($a);

                if ($val) {
                    $this->{$m} = $val;

                    return $this;
                } else {
                    if (is_callable($this->{$m})) {
                        $macro = $this->{$m};
                        $callArgs = array_merge($a, [$this]);

                        $params = !is_array($macro)
                            ? array_merge([$macro], $callArgs)
                            : array_merge($macro, $callArgs)
                        ;

                        return $macro instanceof Closure ?
                            gi()->makeClosure(...$params) :
                            gi()->call(...$params)
                        ;
                    } else {
                        return $this->{$m};
                    }
                }
            } else {
                $key = 'ghost.__macros.' . $this->_instance;
                $macros = Registry::get($key, []);

                $macro = isAke($macros, $m, null);

                if ($macro) {
                    if (is_callable($macro)) {
                        $callArgs = array_merge($a, [$this]);

                        $params = !is_array($macro)
                            ? array_merge([$macro], $callArgs)
                            : array_merge($macro, $callArgs)
                        ;

                        return $macro instanceof Closure ?
                            gi()->makeClosure(...$params) :
                            gi()->call(...$params)
                        ;
                    }
                } else {
                    $closure = current($a);

                    if (is_callable($closure)) {
                        $macros[$m] = $closure;

                        Registry::set($key, $macros);

                        return $this;
                    } else {
                        if (is_string($closure) && fnmatch('*@*', $closure)) {
                            $resolver   = resolverClass($closure);
                            $macros[$m] = $resolver;

                            Registry::set($key, $macros);

                            return $this;
                        } else {
                            if (!empty($closure)) {
                                $this->{$m} = $closure;
                            } else {
                                if ('toArray' === $m) {
                                    return $this->rowing();
                                }

                                throw new \InvalidArgumentException("Method not provided.");
                            }
                        }

                        return $this;
                    }
                }
            }
        }
    }
}
