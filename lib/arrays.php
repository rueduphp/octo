<?php
    namespace Octo;

    use Closure;
    use SplFixedArray as SPA;

    class Arrays
    {
        /**
         * @var string
         */
        public static $delimiter = '.';

        /**
         * @var array
         */
        private static $resources = [];

        /**
         * @param array $array
         *
         * @return SPA
         */
        public static function fixed(array $array = []): SPA
        {
            return SPA::fromArray($array);
        }

        /**
         * @param string $name
         * @param array $array
         *
         * @return bool|resource
         */
        public static function makeResourceWithName(string $name, array $array = [])
        {
            $resource = fopen("php://memory", 'r+');
            fwrite($resource, serialize($array));

            static::$resources[$name] = $resource;

            return $resource;
        }

        /**
         * @param string $name
         * @param array $default
         * @param bool $unserialize
         *
         * @return array|mixed|string
         */
        public static function makeFromResourceName(string $name, array $default = [], bool $unserialize = true)
        {
            $resource = isAke(static::$resources, $name, false);

            if (is_resource($resource)) {
                rewind($resource);

                $cnt = [];

                while (!feof($resource)) {
                    $cnt[] = fread($resource, 1024);
                }

                $data = implode('', $cnt);

                unset(static::$resources[$name]);

                return $unserialize ? unserialize($data) : $data;
            }

            return $unserialize ? $default : serialize($default);
        }

        /**
         * @param array $array
         *
         * @return bool|resource
         */
        public static function makeResource(array $array = [])
        {
            $resource = fopen("php://memory", 'r+');
            fwrite($resource, serialize($array));

            return $resource;
        }

        /**
         * @param $resource
         * @param array $default
         * @param bool $unserialize
         * @return array|mixed|string
         */
        public static function makeFromResource($resource, array $default = [], bool $unserialize = true)
        {
            if (is_resource($resource)) {
                rewind($resource);

                $cnt = [];

                while (!feof($resource)) {
                    $cnt[] = fread($resource, 1024);
                }

                $data = implode('', $cnt);

                return $unserialize ? unserialize($data) : $data;
            }

            return $unserialize ? $default : serialize($default);
        }

        /**
         * @param array $array
         * @param null|string $type
         *
         * @return null|Objet
         */
        public static function setObject(array $array, ?string $type = null)
        {
            if (true === static::isAssoc($array)) {
                $object = new Objet;

                if (!empty($type)) {
                    $object->octo_type = $type;
                }

                foreach ($array as $key => $value) {
                    if (static::is($value)) {
                        $object->{$key} = static::setObject($value);
                    } else {
                        $object->{$key} = $value;
                    }
                }

                return $object;
            }

            return null;
        }

        /**
         * @param $array
         * @param $key
         * @param $value
         * @param string $sep
         * @return mixed
         */
        public static function set(&$array, $key, $value, $sep = '.')
        {
            if (is_null($key)) return $array = $value;

            $keys = explode($sep, $key);

            while (count($keys) > 1) {
                $key = array_shift($keys);

                if (!isset($array[$key]) || !is_array($array[$key])) {
                    $array[$key] = [];
                }

                $array = &$array[$key];
            }

            $array[array_shift($keys)] = $value;

            return $array;
        }

        /**
         * @param array $array
         * @return bool
         */
        public static function isAssoc(array $array)
        {
            $keys = array_keys($array);

            return array_keys($keys) !== $keys;
        }

        /**
         * @param array $array
         * @return array
         */
        public static function divide(array $array): array
        {
            if (static::isAssoc($array)) {
                return [array_keys($array), array_values($array)];
            }

            return [[], []];
        }

        /**
         * @param array $array
         * @return array
         */
        public static function keys(array $array)
        {
            return array_keys($array);
        }

        /**
         * @param $value
         * @return bool
         */
        public static function is($value)
        {
            return static::isArray($value);
        }

        /**
         * @param $value
         * @return bool
         */
        public static function isArray($value)
        {
            if (is_array($value)) {
                return true;
            } else {
                return arrayable($value) || (is_object($value) && $value instanceof \Traversable);
            }
        }

        /**
         * @param $needle
         * @param $array
         * @return bool
         */
        public static function in($needle, $array)
        {
            return static::inArray($needle, $array);
        }

        /**
         * @param $needle
         * @param $array
         * @return bool
         */
        public static function inArray($needle, $array)
        {
            if (static::is($array)) {
                return in_array($needle, $array);
            }

            return false;
        }

        /**
         * @param array $array
         * @param $path
         * @param null $default
         * @param null $delimiter
         * @return array|null
         */
        public static function path(array $array, $path, $default = null, $delimiter = null)
        {
            if (!static::is($array)) {
                return $default;
            }

            if (static::is($path)) {
                $keys = $path;
            } else {
                if (static::exists($path, $array)) {
                    return $array[$path];
                }

                if ($delimiter === null) {
                    $delimiter = static::$delimiter;
                }

                $path = ltrim($path, "{$delimiter} ");

                $path = rtrim($path, "{$delimiter} *");

                $keys = explode($delimiter, $path);
            }

            do {
                $key = array_shift($keys);

                if (ctype_digit($key)) {
                    $key = (int) $key;
                }

                if (isset($array[$key])) {
                    if ($keys) {
                        if (static::is($array[$key])) {
                            $array = $array[$key];
                        } else {
                            break;
                        }
                    } else {
                        return $array[$key];
                    }
                } elseif ($key === '*') {
                    $values = [];

                    foreach ($array as $arr) {
                        if ($value = static::path($arr, implode('.', $keys))) {
                            $values[] = $value;
                        }
                    }

                    if ($values) {
                        return $values;
                    } else {
                        break;
                    }
                } else {
                    break;
                }
            } while ($keys);

            return $default;
        }

        /**
         * @param $array
         * @param $path
         * @param $value
         * @param string $delimiter
         */
        public static function setPath(&$array, $path, $value, $delimiter = '.')
        {
            $keys = explode($delimiter, $path);

            while (count($keys) > 1) {
                $key = array_shift($keys);

                if (ctype_digit($key)) {
                    $key = (int) $key;
                }

                if (!isset($array[$key])) {
                    $array[$key] = [];
                }

                $array = & $array[$key];
            }

            $array[array_shift($keys)] = $value;
        }

        /**
         * @param int $step
         * @param int $max
         * @return array
         */
        public static function range($step = 10, $max = 100)
        {
            if ($step < 1) {
                return [];
            }

            $array = [];

            for ($i = $step; $i <= $max; $i += $step) {
                $array[$i] = $i;
            }

            return $array;
        }

        /**
         * @param $array
         * @param $key
         * @param null $default
         * @param string $sep
         * @return mixed|null
         */
        public static function get($array, $key, $default = null, $sep = '.')
        {
            if (!static::accessible($array)) {
                return value($default);
            }

            if (is_null($key)) {
                return $array;
            }

            if (static::exists($array, $key)) {
                return $array[$key];
            }

            foreach (explode($sep, $key) as $segment) {
                if (static::accessible($array) && static::exists($array, $segment)) {
                    $array = $array[$segment];
                } else {
                    return value($default);
                }
            }

            return $array;
        }

        /**
         * @param $array
         * @param $key
         * @param null $default
         * @param string $sep
         *
         * @return mixed|null
         */
        public static function pull(&$array, $key, $default = null, $sep = '.')
        {
            $value = static::get($array, $key, $default, $sep);

            static::forget($array, $key, $sep);

            return $value;
        }

        public static function forget(&$array, $keys, $sep = '.')
        {
            $original = &$array;

            $keys = (array) $keys;

            if (count($keys) === 0) {
                return;
            }

            foreach ($keys as $key) {
                if (static::exists($array, $key)) {
                    unset($array[$key]);

                    continue;
                }

                $parts = explode($sep, $key);

                $array = &$original;

                while (count($parts) > 1) {
                    $part = array_shift($parts);

                    if (isset($array[$part]) && is_array($array[$part])) {
                        $array = &$array[$part];
                    } else {
                        continue 2;
                    }
                }

                unset($array[array_shift($parts)]);
            }
        }

        public static function only($array, $keys)
        {
            return array_intersect_key($array, array_flip((array) $keys));
        }

        public static function extract($array, array $paths, $default = null)
        {
            $found = [];

            foreach ($paths as $path) {
                static::setPath($found, $path, static::path($array, $path, $default));
            }

            return $found;
        }

        public static function pluck($array, $value, $key = null)
        {
            $results = [];

            list($value, $key) = static::explodePluckParameters($value, $key);

            foreach ($array as $item) {
                $itemValue = dget($item, $value);

                if (is_null($key)) {
                    $results[] = $itemValue;
                } else {
                    $itemKey = dget($item, $key);

                    $results[$itemKey] = $itemValue;
                }
            }

            return $results;
        }

        protected static function explodePluckParameters($value, $key)
        {
            $value = is_string($value) ? explode('.', $value) : $value;

            $key = is_null($key) || is_array($key) ? $key : explode('.', $key);

            return [$value, $key];
        }

        public static function newOne()
        {
            return [];
        }

        public static function unshift(array &$array, $key, $val)
        {
            $array = array_reverse($array, true);
            $array[$key] = $val;
            $array = array_reverse($array, true);

            return $array;
        }

        public static function map($callbacks, $array, $keys = null)
        {
            foreach ($array as $key => $val) {
                if (static::is($val)) {
                    $array[$key] = static::map($callbacks, $array[$key]);
                } elseif (!static::is($keys) || in_array($key, $keys)) {
                    if (static::is($callbacks)) {
                        foreach ($callbacks as $cb) {
                            $array[$key] = call_user_func($cb, $array[$key]);
                        }
                    } else {
                        $array[$key] = call_user_func($callbacks, $array[$key]);
                    }
                }
            }

            return $array;
        }

        public static function merge($array1, $array2)
        {
            if (static::isAssoc($array2)) {
                foreach ($array2 as $key => $value) {
                    if (static::is($value) && isset($array1[$key]) && static::is($array1[$key])) {
                        $array1[$key] = static::merge($array1[$key], $value);
                    } else {
                        $array1[$key] = $value;
                    }
                }
            } else {
                foreach ($array2 as $value) {
                    if (!static::in($value, $array1, true)) {
                        $array1[] = $value;
                    }
                }
            }

            if (func_num_args() > 2) {
                foreach (array_slice(func_get_args(), 2) as $array2) {
                    if (static::isAssoc($array2)) {
                        foreach ($array2 as $key => $value) {
                            if (static::is($value) && isset($array1[$key]) && static::is($array1[$key])) {
                                $array1[$key] = static::merge($array1[$key], $value);
                            } else {
                                $array1[$key] = $value;
                            }
                        }
                    } else {
                        foreach ($array2 as $value) {
                            if (!static::in($value, $array1, true)) {
                                $array1[] = $value;
                            }
                        }
                    }
                }
            }

            return $array1;
        }

        /**
         * @param array $array
         * @param callable|null $callback
         * @param null|string $default
         *
         * @return mixed
         */
        public static function first(array $array, ?callable $callback = null, ?string $default = null)
        {
            if (is_null($callback)) {
                if (empty($array)) {
                    return value($default);
                }

                foreach ($array as $item) {
                    return $item;
                }
            }

            foreach ($array as $key => $value) {
                if (call_user_func($callback, $value, $key)) {
                    return $value;
                }
            }

            return value($default);
        }

        /**
         * @param array $array
         * @param callable|null $callback
         * @param null|string $default
         *
         * @return mixed
         */
        public static function last(array $array, ?callable $callback = null, ?string $default = null)
        {
            if (is_null($callback)) {
                return empty($array) ? value($default) : end($array);
            }

            return static::first(
                array_reverse($array, true),
                $callback,
                $default
            );
        }

        /**
         * @param array $array1
         * @param array $array2
         *
         * @return array
         */
        public static function overwrite(array $array1, array $array2): array
        {
            foreach (array_intersect_key($array2, $array1) as $key => $value) {
                $array1[$key] = $value;
            }

            if (func_num_args() > 2) {
                foreach (array_slice(func_get_args(), 2) as $array2) {
                    foreach (array_intersect_key($array2, $array1) as $key => $value) {
                        $array1[$key] = $value;
                    }
                }
            }

            return $array1;
        }

        /**
         * @param array $array
         * @param string $prepend
         *
         * @return array
         */
        public static function dot(array $array, string $prepend = ''): array
        {
            $results = [];

            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $results = array_merge($results, static::dot($value, $prepend . $key . '.'));
                } else {
                    $results[$prepend . $key] = $value;
                }
            }

            return $results;
        }

        /**
         * @param string $str
         *
         * @return array
         */
        public static function callback(string $str): array
        {
            $params = null;

            if (preg_match('/^([^\(]*+)\((.*)\)$/', $str, $match)) {
                $command = $match[1];

                if ($match[2] !== '') {
                    $params = preg_split('/(?<!\\\\),/', $match[2]);
                    $params = str_replace('\,', ',', $params);
                }
            } else {
                $command = $str;
            }

            if (strpos($command, '::') !== false) {
                $command = explode('::', $command, 2);
            }

            return array($command, $params);
        }

        /**
         * @param array $array
         *
         * @return array
         */
        public static function flatten(array $array): array
        {
            $return = [];

            array_walk_recursive($array, function($x) use (&$return) { $return[] = $x; });

            return $return;
        }

        /**
         * @param array $array
         * @param $value
         *
         * @return array|null
         */
        public static function splitOnValue(array $array, $value): ?array
        {
            if (static::is($array)) {
                $paramPos = array_search($value, $array);

                if ($paramPos) {
                    $arrays[] = array_slice($array, 0, $paramPos);
                    $arrays[] = array_slice($array, $paramPos + 1);
                } else {
                    $arrays = null;
                }

                if (static::is($arrays)) {
                    return $arrays;
                }
            }

            return null;
        }

        public static function makeHashFromArray($array)
        {
            $hash = null;

            if (static::is($array) && count($array) > 1) {
                for ($i = 0; $i <= count($array); $i += 2) {
                    if (isset($array[$i])) {
                        $key = $array[$i];
                        $value = $array[$i + 1];

                        if (!empty($key) && !empty($value)) {
                           $hash[$key] = $value;
                        }
                    }
                }
            }

            if (true === static::is($hash)) {
                return $hash;
            }
        }

        /**
         * @param array $groups
         *
         * @return array
         */
        public static function splitGroups(array $groups): array
        {
            $g = $arrFirst = $arrSecond = $arrReturn = $totalItems = $count = [];

            foreach ($groups as $k => $v) {
                $g[$k] = strlen($v);
                $totalItems += $g[$k];
            }

            $firstHalfCount = ceil($totalItems / 2);

            $first = true;

            foreach ($g as $k => $v) {
                if ($first) {
                    $arrFirst[$k] = $groups[$k];
                    $count += $v;

                    if ($count > $firstHalfCount) {
                        $first = false;
                    }
                } else {
                    $arrSecond[$k] = $groups[$k];
                }
            }

            $arrReturn['first']     = $arrFirst;
            $arrReturn['second']    = $arrSecond;

            return $arrReturn;
        }

        /**
         * @param array $getParams
         *
         * @return mixed
         */
        public static function arrayFromGet(array $getParams)
        {
            $parts = explode('&', $getParams);

            if (static::is($parts)) {
                foreach ($parts as $part) {
                    $paramParts = explode('=', $part);

                    if (static::is($paramParts) && count($paramParts) == 2) {
                        $param[current($paramParts)] = end($paramParts);
                        unset($paramParts);
                    }
                }
            }

            return $param;
        }

        /**
         * @param mixed $array
         * @param string $key
         *
         * @return bool
         */
        public static function exists($array, string $key): bool
        {
            if ($array instanceof \ArrayAccess) {
                return $array->offsetExists($key);
            }

            return array_key_exists($key, $array);
        }

        /**
         * @param array $tab
         * @param int $index
         *
         * @return mixed|null
         */
        public static function indexReverse(array $tab, int $index = 1)
        {
            if (!empty($tab)) {
                $needle = count($tab) - $index;

                if (isset($tab[$needle])) {
                    return $tab[$needle];
                }
            }

            return null;
        }

        /**
         * @param array $tab
         * @param int $index
         *
         * @return mixed|null
         */
        public static function index(array $tab, int $index = 1)
        {
            if (!empty($tab)) {
                if (isset($tab[$index])) {
                    return $tab[$index];
                }
            }

            return null;
        }

        public static function sort($array, \Closure $callback)
        {
            return coll($array)->sortBy($callback)->all();
        }

        /**
         * @param array $array
         * @param Closure $callback
         *
         * @return array
         */
        public static function where(array $array, Closure $callback)
        {
            $filtered = [];

            foreach ($array as $key => $value) {
                if (call_user_func($callback, $key, $value)) {
                    $filtered[$key] = $value;
                }
            }

            return $filtered;
        }

        /**
         * @param array $array
         *
         * @return Collection
         */
        public static function toCollection(array $array): Collection
        {
            return new Collection($array);
        }

        /**
         * @param array $array
         * @return array
         */
        public static function stringParams(array $array)
        {
            return static::where(
                $array,
                function($k, $v) { return is_string($k); }
            );
        }


        /**
         * @param $array
         * @return array
         */
        public static function numericParams($array)
        {
            return static::where(
                $array,
                function($k, $v) { return is_numeric($k); }
            );
        }

        public static function __callStatic($method, $args)
        {
            if (is_callable($method)) {
                if (count($args) == 0) {
                    return $method();
                } elseif (count($args) == 1) {
                    $arg = static::first($args);

                    return $method($arg);
                } elseif (count($args) == 2) {
                    $arg1 = static::first($args);
                    $arg2 = static::last($args);

                    return $method($arg1, $arg2);
                } else {
                    return call_user_func_array($method, $args);
                }
            }

            $method = 'array_' . $method;

            if (is_callable($method)) {
                if (count($args) == 0) {
                    return $method();
                } elseif (count($args) == 1) {
                    $arg = static::first($args);

                    return $method($arg);
                } elseif (count($args) == 2) {
                    $arg1 = static::first($args);
                    $arg2 = static::last($args);

                    return $method($arg1, $arg2);
                } else {
                    return call_user_func_array($method, $args);
                }
            }
        }

        public static function except($array, $keys)
        {
            static::forget($array, $keys);

            return $array;
        }

        public static function fetch($array, $key, $separator = '.')
        {
            foreach (explode($separator, $key) as $segment) {
                $results = [];

                foreach ($array as $value) {
                    $value = (array) $value;
                    $results[] = $value[$segment];
                }

                $array = array_values($results);
            }

            return array_values($results);
        }

        public static function sep($array, $prepend = '', $separator = '.')
        {
            $results = [];

            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $results = array_merge(
                        $results,
                        static::dot(
                            $value,
                            $prepend . $key . $separator
                        )
                    );
                } else {
                    $results[$prepend . $key] = $value;
                }
            }

            return $results;
        }

        public static function build($array, \Closure $callback)
        {
            $results = [];

            foreach ($array as $key => $value) {
                list($innerKey, $innerValue) = call_user_func($callback, $key, $value);
                $results[$innerKey] = $innerValue;
            }

            return $results;
        }

        public static function pick($tab, $key, $default = null)
        {
            return isAke($tab, $key, $default);
        }

        public static function fetchOne($array, $key, $sep = '.')
        {
            foreach (explode($sep, $key) as $segment) {
                $results = [];

                foreach ($array as $value) {
                    if (array_key_exists($segment, $value = (array) $value)) {
                        $results[] = $value[$segment];
                    }
                }

                $array = array_values($results);
            }

            return array_values($results);
        }

        public static function firstOne($array, callable $callback, $default = null)
        {
            foreach ($array as $key => $value) {
                if (call_user_func($callback, $key, $value)) {
                    return $value;
                }
            }

            return value($default);
        }

        public static function lastOne($array, callable $callback, $default = null)
        {
            return self::first(array_reverse($array), $callback, $default);
        }

        public static function data_get($target, $key, $default = null)
        {
            if (is_null($key)) {
                return $target;
            }

            foreach (explode('.', $key) as $segment) {
                if (is_array($target)) {
                    if (!array_key_exists($segment, $target)) {
                        return File::value($default);
                    }

                    $target = $target[$segment];
                } elseif ($target instanceof \ArrayAccess) {
                    if (!isset($target[$segment])) {
                        return value($default);
                    }

                    $target = $target[$segment];
                } elseif (is_object($target)) {
                    if (!isset($target->{$segment})) {
                        return value($default);
                    }

                    $target = $target->{$segment};
                } else {
                    return value($default);
                }
            }

            return $target;
        }

        public static function pattern($array, $pattern = '*')
        {
            $array = arrayable($array) ? $array->toArray() : $array;

            $collection = [];

            if (self::isAssoc($array)) {
                foreach ($array as $k => $v) {
                    if (fnmatch($pattern, $k)) {
                        $collection[$k] = $v;
                    }
                }
            } else {
                foreach ($array as $k) {
                    if (fnmatch($pattern, $k)) {
                        $collection[] = $k;
                    }
                }
            }

            return $collection;
        }

        public static function collapse($array)
        {
            $results = [];

            foreach ($array as $values) {
                if ($values instanceof Collection) {
                    $values = $values->all();
                } elseif (!is_array($values)) {
                    continue;
                }

                $results = array_merge($results, $values);
            }

            return $results;
        }

        public static function accessible($value)
        {
            return is_array($value) || $value instanceof \ArrayAccess;
        }

        public static function has($array, $keys)
        {
            if (is_null($keys)) {
                return false;
            }

            $keys = (array) $keys;

            if (! $array) {
                return false;
            }

            if ($keys === []) {
                return false;
            }

            foreach ($keys as $key) {
                $subKeyArray = $array;

                if (static::exists($array, $key)) {
                    continue;
                }

                foreach (explode('.', $key) as $segment) {
                    if (static::accessible($subKeyArray) && static::exists($subKeyArray, $segment)) {
                        $subKeyArray = $subKeyArray[$segment];
                    } else {
                        return false;
                    }
                }
            }

            return true;
        }

        public static function prepend($array, $value, $key = null)
        {
            if (is_null($key)) {
                array_unshift($array, $value);
            } else {
                $array = [$key => $value] + $array;
            }

            return $array;
        }

        /**
         * @param array $array
         * @param string $key
         * @param $value
         *
         * @return array
         */
        public static function add(array $array, string $key, $value)
        {
            if (is_null(static::get($array, $key))) {
                static::set($array, $key, $value);
            }
            return $array;
        }


        /**
         * @param array $array
         * @param string $key
         * @param $value
         *
         * @return array
         */
        public static function findBy(array $array, string $key, $value): array
        {
            return static::toCollection($array)->where($key, $value)->all();
        }

        /**
         * @param array $array
         * @param callable $callable
         * @return mixed
         */
        public static function reduce(array $array, callable $callable)
        {
            return static::toCollection($array)->reduce($callable);
        }

        /**
         * @param $value
         * @param bool $allowEmpty
         *
         * @return bool
         */
        public static function hasNumericKeys($value, $allowEmpty = false)
        {
            if (!is_array($value)) {
                return false;
            }

            if (!$value) {
                return $allowEmpty;
            }

            return count(array_filter(array_keys($value), 'is_numeric')) > 0;
        }

        /**
         * @param $value
         * @param bool $allowEmpty
         *
         * @return bool
         */
        public static function isList($value, $allowEmpty = false)
        {
            if (!is_array($value)) {
                return false;
            }

            if (empty($value)) {
                return $allowEmpty;
            }

            return array_values($value) === $value;
        }

        /**
         * @param SPA $arr
         * @param int $size
         *
         * @return SPA
         */
        public static function chunk_fixed(SPA $arr, $size)
        {
            $chunks = new SPA(ceil(count($arr) / $size));

            foreach ($arr as $idx => $value) {
                if ($idx % $size === 0) {
                    $chunks[$idx / $size] = $chunk = new SPA($size);
                }

                $chunk[$idx % $size] = $value;
            }

            $chunk->setSize(count($arr) % $size);

            return $chunks;
        }

        /**
         * @param array $data
         * @param $callback
         * @param null $flag
         *
         * @return array
         *
         * @throws \Exception
         */
        public static function filter(array $data, $callback, $flag = null)
        {
            if (!is_callable($callback)) {
                throw new \Exception(sprintf(
                    'Second parameter of %s must be callable',
                    __METHOD__
                ));
            }

            if (version_compare(PHP_VERSION, '5.6.0') >= 0) {
                return array_filter($data, $callback, $flag);
            }

            $output = [];

            foreach ($data as $key => $value) {
                $params = [$value];

                if ($flag === 1) {
                    $params[] = $key;
                }

                if ($flag === 2) {
                    $params = [$key];
                }

                $response = call_user_func_array($callback, $params);

                if (true === $response) {
                    $output[$key] = $value;
                }
            }

            return $output;
        }

        /**
         * @param array $fields
         * @param string $delimiter
         * @param string $enclosure
         * @param bool $encloseAll
         * @param bool $nullToMysqlNull
         *
         * @return string
         */
        public static function toCsv(
            array $fields,
            string $delimiter = ';',
            string $enclosure = '"',
            bool $encloseAll = false,
            bool $nullToMysqlNull = false
        ): string {
            $delimiter_esc = preg_quote($delimiter, '/');
            $enclosure_esc = preg_quote($enclosure, '/');

            $output = [];

            foreach ($fields as $field) {
                if (null === $field && true === $nullToMysqlNull) {
                    $output[] = 'NULL';

                    continue;
                }

                if (true === $encloseAll || preg_match("/(?:${delimiter_esc}|${enclosure_esc}|\s)/", $field)) {
                    $output[] = $enclosure . str_replace(
                        $enclosure,
                        $enclosure . $enclosure,
                        $field
                    ) . $enclosure;
                } else {
                    $output[] = $field;
                }
            }

            return implode($delimiter, $output);
        }

        /**
         * @param array $rows
         *
         * @return string
         */
        public static function toHtml(array $rows): string
        {
            $html = "";

            if (!empty($rows)) {
                $labels = array_keys(current($rows));
                $html .= '<table>';

                $html .= '<thead>';
                $html .= '<tr>';

                foreach ($labels as $label) {
                    $html .= '<th>' . $label . '</th>';
                }

                $html .= '</tr>';
                $html .= '</thead>';

                $html .= '<tbody>';

                foreach ($rows as $row) {
                    $html .= '<tr>';

                    foreach ($row as $label => $value) {
                        $html .= '<td>' . $value . '</td>';
                    }

                    $html .= '</tr>';
                }

                $html .= '</tbody></table>';
            }

            return $html;
        }

        /**
         * @param array ...$arrays
         * @return array
         */
        public static function crossJoin(...$arrays)
        {
            $results = [[]];

            foreach ($arrays as $index => $array) {
                $append = [];

                foreach ($results as $product) {
                    foreach ($array as $item) {
                        $product[$index] = $item;

                        $append[] = $product;
                    }
                }

                $results = $append;
            }

            return $results;
        }
    }
