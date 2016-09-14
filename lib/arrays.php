<?php
    namespace Octo;

    class Arrays
    {
        public static $delimiter = '.';

        private static $resources = [];

        public static function fixed(array $array = [])
        {
            return \SplFixedArray::fromArray($array);
        }

        public static function makeResourceWithName($name, array $array = [])
        {
            $resource = fopen("php://memory", 'r+');
            fwrite($resource, serialize($array));

            self::$resources[$name] = $resource;

            return $this;
        }

        public static function makeFromResourceName($name, $default = [], $unserialize = true)
        {
            $resource = isAke(self::$resources, $name, false);

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

        public static function makeResource(array $array = [])
        {
            $resource = fopen("php://memory", 'r+');
            fwrite($resource, serialize($array));

            return $resource;
        }

        public static function makeFromResource($resource, $default = [], $unserialize = true)
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

        public static function setObject(array $array, $type = null)
        {
            if (true === static::isAssoc($array)) {
                $object = new Object;

                if (!empty($type)) {
                    $object->octo_type = $type;
                }

                foreach ($array as $key => $value) {
                    if (static::is($value)) {
                        $object->$key = static::setObject($value);
                    } else {
                        $object->$key = $value;
                    }
                }

                return $object;
            }

            return null;
        }

        public static function set(&$array, $key, $value, $sep = '.')
        {
            if (is_null($key)) return $array = $value;

            $keys = explode($sep, $key);

            while (count($keys) > 1) {
                $key = array_shift($keys);

                if (!isset($array[$key]) || ! is_array($array[$key])) {
                    $array[$key] = array();
                }

                $array =& $array[$key];
            }

            $array[array_shift($keys)] = $value;

            return $array;
        }

        public static function isAssoc(array $array)
        {
            $keys = array_keys($array);

            return array_keys($keys) !== $keys;
        }

        public static function keys(array $array)
        {
            return array_keys($array);
        }

        public static function is($value)
        {
            return static::isArray($value);
        }

        public static function isArray($value)
        {
            if (is_array($value)) {
                return true;
            } else {
                return (is_object($value) && $value instanceof \Traversable);
            }
        }

        public static function in($needle, $array)
        {
            return static::inArray($needle, $array);
        }

        public static function inArray($needle, $array)
        {
            if (static::is($array)) {
                return in_array($needle, $array);
            }

            return false;
        }

        public static function path($array, $path, $default = null, $delimiter = null)
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
                    $values = array();

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

        public static function setPath(&$array, $path, $value, $delimiter = '.')
        {
            $keys = explode($delimiter, $path);

            while (count($keys) > 1) {
                $key = array_shift($keys);

                if (ctype_digit($key)) {
                    $key = (int) $key;
                }

                if ( ! isset($array[$key])) {
                    $array[$key] = array();
                }

                $array = & $array[$key];
            }

            $array[array_shift($keys)] = $value;
        }

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

        public static function get($array, $key, $default = null, $sep = '.')
        {
            if (is_null($key)) {
                return $array;
            }

            if (isset($array[$key])) {
                return $array[$key];
            }

            foreach (explode($sep, $key) as $segment) {
                if (!is_array($array) || !array_key_exists($segment, $array)) {
                    return File::value($default);
                }

                $array = $array[$segment];
            }

            return $array;
        }

        public static function pull(&$array, $key, $default = null)
        {
            $value = static::get($array, $key, $default);

            static::forget($array, $key);

            return $value;
        }

        public static function forget(&$array, $keys, $sep = '.')
        {
            $original =& $array;

            foreach ((array) $keys as $key) {
                $parts = explode($sep, $key);

                while (count($parts) > 1) {
                    $part = array_shift($parts);

                    if (isset($array[$part]) && is_array($array[$part])) {
                        $array =& $array[$part];
                    }
                }

                unset($array[array_shift($parts)]);

                $array =& $original;
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

        public static function pluck($array, $key)
        {
            $values = [];

            foreach ($array as $row) {
                if (isset($row[$key])) {
                    $values[] = $row[$key];
                }
            }

            return $values;
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

        public static function first(array $tab)
        {
            if (!empty($tab)) {
                return current($tab);
            }

            return null;
        }

        public static function last(array $tab)
        {
            if (!empty($tab)) {
                return end($tab);
            }

            return null;
        }

        public static function overwrite(array $array1, array $array2)
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

        public static function dot($array, $prepend = '')
        {
            $results = array();

            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $results = array_merge($results, static::dot($value, $prepend . $key . '.'));
                } else {
                    $results[$prepend . $key] = $value;
                }
            }

            return $results;
        }

        public static function callback($str)
        {
            $command = $params = null;

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

        public static function flatten($array)
        {
            $return = array();

            array_walk_recursive($array, function($x) use (&$return) { $return[] = $x; });

            return $return;
        }

        public static function splitOnValue($array, $value)
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

        public static function splitGroups($groups)
        {
            foreach ($groups as $k => $v) {
                //set up an array of key = count
                $g[$k] = strlen($v);
                $totalItems += $g[$k];
            }

            //the first half is the larger of the two
            $firstHalfCount = ceil($totalItems / 2);

            //now go through the array and add the items to the two groups.
            $first=true;

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

        public static function arrayFromGet($getParams)
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

        public static function exists($key, array $search)
        {
            return array_key_exists($key, $search);
        }

        public static function indexReverse(array $tab, $index = 1)
        {
            if (!empty($tab)) {
                if (isset($tab[count($tab) - $index])) {
                    return $tab[count($tab) - $index];
                }
            }

            return null;
        }

        public static function index(array $tab, $index = 1)
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

        public static function where($array, \Closure $callback)
        {
            $filtered = array();

            foreach ($array as $key => $value) {
                if (call_user_func($callback, $key, $value)) {
                    $filtered[$key] = $value;
                }
            }

            return $filtered;
        }

        public static function stringParams($array)
        {
            return static::where($array, function($k, $v) { return is_string($k); });
        }


        public static function numericParams($array)
        {
            return static::where($array, function($k, $v) { return is_numeric($k); });
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
            return array_diff_key($array, array_flip((array) $keys));
        }

        public static function fetch($array, $key, $separator = '.')
        {
            foreach (explode($separator, $key) as $segment) {
                $results = array();

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
            $results = array();

            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $results = array_merge($results, static::dot($value, $prepend . $key . $separator));
                } else {
                    $results[$prepend . $key] = $value;
                }
            }

            return $results;
        }

        public static function build($array, \Closure $callback)
        {
            $results = array();

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

            return File::value($default);
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
                        return File::value($default);
                    }

                    $target = $target[$segment];
                } elseif (is_object($target)) {
                    if (!isset($target->{$segment})) {
                        return File::value($default);
                    }

                    $target = $target->{$segment};
                } else {
                    return File::value($default);
                }
            }

            return $target;
        }

        public static function pattern(array $array, $pattern = '*')
        {
            $collection = [];

            if (self::isAssoc($array)) {
                foreach ($array as $k => $v) {
                    if (fnmatch($pattern, $k)) {
                        $collection[$k] = $v;
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
    }
