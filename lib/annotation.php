<?php
namespace Octo;

use ReflectionClass;
use ReflectionFunction;

/**
 * @package Octo
 */
class Annotation
{
    protected const ANNOTATION_REGEX = '/@(\w+)(?:\s*(?:\(\s*)?(.*?)(?:\s*\))?)??\s*(?:\n|\*\/)/';
    protected const PARAMETER_REGEX = '/(\w+)\s*=\s*(\[[^\]]*\]|"[^"]*"|[^,)]*)\s*(?:,|$)/';

    /**
     * @param $class
     * @param $property
     *
     * @return array
     *
     * @throws \ReflectionException
     */
    public static function property($class, $property): array
    {
        $reflection = new ReflectionClass(static::getClass($class));
        $property   = $reflection->getProperty($property);

        return static::parse($reflection->getProperty($property));
    }

    /**
     * @param $class
     * @param string $method
     *
     * @return array
     *
     * @throws \ReflectionException
     */
    public static function method($class, string $method): array
    {
        $reflection = new ReflectionClass(static::getClass($class));
        $method     = $reflection->getMethod($method);

        return static::parse($method->getDocComment());
    }

    /**
     * @param $function
     *
     * @return array
     *
     * @throws \ReflectionException
     */
    public static function func($function): array
    {
        $reflection = new ReflectionFunction($function);

        return static::parse($reflection->getDocComment());
    }

    /**
     * @param string $method
     * @param array $args
     *
     * @return mixed
     */
    public static function __callStatic(string $method, array $args)
    {
        if ('function' === $method) {
            return forward_static_call_array([__CLASS__, 'func'], $args);
        }
    }

    /**
     * @param $class
     *
     * @return array
     *
     * @throws \ReflectionException
     */
    public static function class($class): array
    {
        $reflection = new ReflectionClass(static::getClass($class));

        return static::parse($reflection->getDocComment());
    }

    /**
     * @param $class
     *
     * @return string
     */
    protected static function getClass($class): string
    {
        return is_object($class) ? get_class($class) : $class;
    }

    /**
     * @param string $docComment
     *
     * @return array
     */
    protected static function parse(string $docComment)
    {
        $hasAnnotations = preg_match_all(
            static::ANNOTATION_REGEX,
            $docComment,
            $matches,
            PREG_SET_ORDER
        );

        if (!$hasAnnotations) {
            return [];
        }

        $annotations = [];

        foreach ($matches as $anno) {
            $annoName = Inflector::lower($anno[1]);
            $val = true;

            if (isset($anno[2])) {
                $hasParams = preg_match_all(
                    self::PARAMETER_REGEX,
                    $anno[2],
                    $params,
                    PREG_SET_ORDER
                );

                if ($hasParams) {
                    $val = [];

                    foreach ($params as $param) {
                        $val[$param[1]] = static::value($param[2]);
                    }
                } else {
                    $val = trim($anno[2]);

                    if ($val === '') {
                        $val = true;
                    } else {
                        $val = static::value($val);
                    }
                }
            }

            if (isset($annotations[$annoName])) {
                if (!is_array($annotations[$annoName])) {
                    $annotations[$annoName] = array($annotations[$annoName]);
                }

                $annotations[$annoName][] = $val;
            } else {
                $annotations[$annoName] = $val;
            }
        }

        return $annotations;
    }

    /**
     * @param string $value
     *
     * @return array|bool|float|int|mixed|string
     */
    protected static function value(string $value)
    {
        $val = trim($value);

        if (substr($val, 0, 1) === '[' && substr($val, -1) === ']') {
            $vals = explode(',', substr($val, 1, -1));
            $val = [];

            foreach ($vals as $v) {
                $val[] = static::value($v);
            }

            return $val;
        } elseif (substr($val, 0, 1) === '{' && substr($val, -1) === '}') {
                return json_decode($val);
        } elseif (substr($val, 0, 1) === '"' && substr($val, -1) === '"') {
            $val = substr($val, 1, -1);

            return static::value($val);
        } elseif (Inflector::lower($val) === 'true') {
            return true;
        } elseif (Inflector::lower($val) === 'false') {
            return false;
        } elseif (is_numeric($val)) {
            if ((float) $val === (int) $val) {
                return (int) $val;
            } else {
                return (float) $val;
            }
        } else {
            return $val;
        }
    }
}
