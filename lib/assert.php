<?php
namespace Octo;

use InvalidArgumentException;
use Traversable;

class Assert
{
    public static function string($value, $message = '')
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a string. Got: %s',
                self::typeToString($value)
            ));
        }
    }

    public static function stringNotEmpty($value, $message = '')
    {
        self::string($value, $message);
        self::notEmpty($value, $message);
    }

    public static function integer($value, $message = '')
    {
        if (!is_int($value)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected an integer. Got: %s',
                self::typeToString($value)
            ));
        }
    }

    public static function integerish($value, $message = '')
    {
        if (!is_numeric($value) || $value != (int) $value) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected an integerish value. Got: %s',
                self::typeToString($value)
            ));
        }
    }

    public static function float($value, $message = '')
    {
        if (!is_float($value)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a float. Got: %s',
                self::typeToString($value)
            ));
        }
    }

    public static function numeric($value, $message = '')
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a numeric. Got: %s',
                self::typeToString($value)
            ));
        }
    }

    public static function boolean($value, $message = '')
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a boolean. Got: %s',
                self::typeToString($value)
            ));
        }
    }

    public static function scalar($value, $message = '')
    {
        if (!is_scalar($value)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a scalar. Got: %s',
                self::typeToString($value)
            ));
        }
    }

    public static function resource($value, $type = null, $message = '')
    {
        if (!is_resource($value)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a resource. Got: %s',
                self::typeToString($value)
            ));
        }

        if ($type && $type !== get_resource_type($value)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a resource of type %2$s. Got: %s',
                self::typeToString($value),
                $type
            ));
        }
    }

    public static function isCallable($value, $message = '')
    {
        if (!is_callable($value)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a callable. Got: %s',
                self::typeToString($value)
            ));
        }
    }

    public static function isArray($value, $message = '')
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected an array. Got: %s',
                self::typeToString($value)
            ));
        }
    }

    public static function isTraversable($value, $message = '')
    {
        if (!is_array($value) && !($value instanceof Traversable)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a traversable. Got: %s',
                self::typeToString($value)
            ));
        }
    }

    public static function isInstanceOf($value, $class, $message = '')
    {
        if (!($value instanceof $class)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected an instance of %2$s. Got: %s',
                self::typeToString($value),
                $class
            ));
        }
    }

    public static function notInstanceOf($value, $class, $message = '')
    {
        if ($value instanceof $class) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected an instance other than %2$s. Got: %s',
                self::typeToString($value),
                $class
            ));
        }
    }

    public static function isEmpty($value, $message = '')
    {
        if (!empty($value)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected an empty value. Got: %s',
                self::valueToString($value)
            ));
        }
    }

    public static function notEmpty($value, $message = '')
    {
        if (empty($value)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a non-empty value. Got: %s',
                self::valueToString($value)
            ));
        }
    }

    public static function null($value, $message = '')
    {
        if (null !== $value) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected null. Got: %s',
                self::valueToString($value)
            ));
        }
    }

    public static function notNull($value, $message = '')
    {
        if (null === $value) {
            throw new InvalidArgumentException(
                $message ?: 'Expected a value other than null.'
            );
        }
    }

    public static function true($value, $message = '')
    {
        if (true !== $value) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value to be true. Got: %s',
                self::valueToString($value)
            ));
        }
    }

    public static function false($value, $message = '')
    {
        if (false !== $value) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value to be false. Got: %s',
                self::valueToString($value)
            ));
        }
    }

    public static function eq($value, $value2, $message = '')
    {
        if ($value2 != $value) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value equal to %2$s. Got: %s',
                self::valueToString($value),
                self::valueToString($value2)
            ));
        }
    }

    public static function notEq($value, $value2, $message = '')
    {
        if ($value2 == $value) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a different value than %s.',
                self::valueToString($value2)
            ));
        }
    }

    public static function same($value, $value2, $message = '')
    {
        if ($value2 !== $value) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value identical to %2$s. Got: %s',
                self::valueToString($value),
                self::valueToString($value2)
            ));
        }
    }

    public static function notSame($value, $value2, $message = '')
    {
        if ($value2 === $value) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value not identical to %s.',
                self::valueToString($value2)
            ));
        }
    }

    public static function greaterThan($value, $limit, $message = '')
    {
        if ($value <= $limit) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value greater than %2$s. Got: %s',
                self::valueToString($value),
                self::valueToString($limit)
            ));
        }
    }

    public static function greaterThanEq($value, $limit, $message = '')
    {
        if ($value < $limit) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value greater than or equal to %2$s. Got: %s',
                self::valueToString($value),
                self::valueToString($limit)
            ));
        }
    }

    public static function lessThan($value, $limit, $message = '')
    {
        if ($value >= $limit) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value less than %2$s. Got: %s',
                self::valueToString($value),
                self::valueToString($limit)
            ));
        }
    }

    public static function lessThanEq($value, $limit, $message = '')
    {
        if ($value > $limit) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value less than or equal to %2$s. Got: %s',
                self::valueToString($value),
                self::valueToString($limit)
            ));
        }
    }

    public static function range($value, $min, $max, $message = '')
    {
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value between %2$s and %3$s. Got: %s',
                self::valueToString($value),
                self::valueToString($min),
                self::valueToString($max)
            ));
        }
    }

    public static function oneOf($value, array $values, $message = '')
    {
        if (!in_array($value, $values, true)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected one of: %2$s. Got: %s',
                self::valueToString($value),
                implode(', ', array_map(array(__CLASS__, 'valueToString'), $values))
            ));
        }
    }

    public static function contains($value, $subString, $message = '')
    {
        if (false === strpos($value, $subString)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value to contain %2$s. Got: %s',
                self::valueToString($value),
                self::valueToString($subString)
            ));
        }
    }

    public static function startsWith($value, $prefix, $message = '')
    {
        if (0 !== strpos($value, $prefix)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value to start with %2$s. Got: %s',
                self::valueToString($value),
                self::valueToString($prefix)
            ));
        }
    }

    public static function startsWithLetter($value, $message = '')
    {
        $valid = isset($value[0]);

        if ($valid) {
            $locale = setlocale(LC_CTYPE, 0);
            setlocale(LC_CTYPE, 'C');
            $valid = ctype_alpha($value[0]);
            setlocale(LC_CTYPE, $locale);
        }

        if (!$valid) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value to start with a letter. Got: %s',
                self::valueToString($value)
            ));
        }
    }

    public static function endsWith($value, $suffix, $message = '')
    {
        if ($suffix !== substr($value, -self::strlen($suffix))) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value to end with %2$s. Got: %s',
                self::valueToString($value),
                self::valueToString($suffix)
            ));
        }
    }

    public static function regex($value, $pattern, $message = '')
    {
        if (!preg_match($pattern, $value)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'The value %s does not match the expected pattern.',
                self::valueToString($value)
            ));
        }
    }

    public static function alpha($value, $message = '')
    {
        $locale = setlocale(LC_CTYPE, 0);
        setlocale(LC_CTYPE, 'C');
        $valid = !ctype_alpha($value);
        setlocale(LC_CTYPE, $locale);

        if ($valid) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value to contain only letters. Got: %s',
                self::valueToString($value)
            ));
        }
    }

    public static function digits($value, $message = '')
    {
        $locale = setlocale(LC_CTYPE, 0);
        setlocale(LC_CTYPE, 'C');
        $valid = !ctype_digit($value);
        setlocale(LC_CTYPE, $locale);

        if ($valid) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value to contain digits only. Got: %s',
                self::valueToString($value)
            ));
        }
    }

    public static function alnum($value, $message = '')
    {
        $locale = setlocale(LC_CTYPE, 0);
        setlocale(LC_CTYPE, 'C');
        $valid = !ctype_alnum($value);
        setlocale(LC_CTYPE, $locale);

        if ($valid) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value to contain letters and digits only. Got: %s',
                self::valueToString($value)
            ));
        }
    }

    public static function lower($value, $message = '')
    {
        $locale = setlocale(LC_CTYPE, 0);
        setlocale(LC_CTYPE, 'C');
        $valid = !ctype_lower($value);
        setlocale(LC_CTYPE, $locale);

        if ($valid) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value to contain lowercase characters only. Got: %s',
                self::valueToString($value)
            ));
        }
    }

    public static function upper($value, $message = '')
    {
        $locale = setlocale(LC_CTYPE, 0);
        setlocale(LC_CTYPE, 'C');
        $valid = !ctype_upper($value);
        setlocale(LC_CTYPE, $locale);

        if ($valid) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value to contain uppercase characters only. Got: %s',
                self::valueToString($value)
            ));
        }
    }

    public static function length($value, $length, $message = '')
    {
        if ($length !== self::strlen($value)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value to contain %2$s characters. Got: %s',
                self::valueToString($value),
                $length
            ));
        }
    }

    public static function minLength($value, $min, $message = '')
    {
        if (self::strlen($value) < $min) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value to contain at least %2$s characters. Got: %s',
                self::valueToString($value),
                $min
            ));
        }
    }

    public static function maxLength($value, $max, $message = '')
    {
        if (self::strlen($value) > $max) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value to contain at most %2$s characters. Got: %s',
                self::valueToString($value),
                $max
            ));
        }
    }

    public static function lengthBetween($value, $min, $max, $message = '')
    {
        $length = self::strlen($value);

        if ($length < $min || $length > $max) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a value to contain between %2$s and %3$s characters. Got: %s',
                self::valueToString($value),
                $min,
                $max
            ));
        }
    }

    public static function fileExists($value, $message = '')
    {
        self::string($value);

        if (!file_exists($value)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'The file %s does not exist.',
                self::valueToString($value)
            ));
        }
    }

    public static function file($value, $message = '')
    {
        self::fileExists($value, $message);

        if (!is_file($value)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'The path %s is not a file.',
                self::valueToString($value)
            ));
        }
    }

    public static function directory($value, $message = '')
    {
        self::fileExists($value, $message);

        if (!is_dir($value)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'The path %s is no directory.',
                self::valueToString($value)
            ));
        }
    }

    public static function readable($value, $message = '')
    {
        if (!is_readable($value)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'The path %s is not readable.',
                self::valueToString($value)
            ));
        }
    }

    public static function writable($value, $message = '')
    {
        if (!is_writable($value)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'The path %s is not writable.',
                self::valueToString($value)
            ));
        }
    }

    public static function email($value, $message = '')
    {
        $check = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;

        if (false === $check) {
            throw new InvalidArgumentException(sprintf(
                $message ?: '%s is not a valid email.',
                self::valueToString($value)
            ));
        }
    }

    public static function classExists($value, $message = '')
    {
        if (!class_exists($value)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected an existing class name. Got: %s',
                self::valueToString($value)
            ));
        }
    }

    public static function subclassOf($value, $class, $message = '')
    {
        if (!is_subclass_of($value, $class)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected a sub-class of %2$s. Got: %s',
                self::valueToString($value),
                self::valueToString($class)
            ));
        }
    }

    public static function implementsInterface($value, $interface, $message = '')
    {
        if (!in_array($interface, class_implements($value))) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected an implementation of %2$s. Got: %s',
                self::valueToString($value),
                self::valueToString($interface)
            ));
        }
    }

    public static function keyExists($array, $key, $message = '')
    {
        if (!array_key_exists($key, $array)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected the key %s to exist.',
                self::valueToString($key)
            ));
        }
    }

    public static function keyNotExists($array, $key, $message = '')
    {
        if (array_key_exists($key, $array)) {
            throw new InvalidArgumentException(sprintf(
                $message ?: 'Expected the key %s to not exist.',
                self::valueToString($key)
            ));
        }
    }

    public static function __callStatic($name, $arguments)
    {
        if ('nullOr' === substr($name, 0, 6)) {
            if (null !== $arguments[0]) {
                $method = lcfirst(substr($name, 6));
                call_user_func_array(array('static', $method), $arguments);
            }

            return;
        }

        if ('all' === substr($name, 0, 3)) {
            self::isTraversable($arguments[0]);

            $method = lcfirst(substr($name, 3));
            $args = $arguments;

            foreach ($arguments[0] as $entry) {
                $args[0] = $entry;

                forward_static_call([Assert::class, $method], $args);
            }

            return;
        }

        throw new \BadMethodCallException('No such method: ' . $name);
    }

    protected static function valueToString($value)
    {
        if (null === $value) {
            return 'null';
        }

        if (true === $value) {
            return 'true';
        }

        if (false === $value) {
            return 'false';
        }

        if (is_array($value)) {
            return 'array';
        }

        if (is_object($value)) {
            return get_class($value);
        }

        if (is_resource($value)) {
            return 'resource';
        }

        if (is_string($value)) {
            return '"' . $value . '"';
        }

        return (string) $value;
    }

    protected static function typeToString($value)
    {
        return is_object($value) ? get_class($value) : gettype($value);
    }

    protected static function strlen($value)
    {
        if (!function_exists('mb_detect_encoding')) {
            return strlen($value);
        }

        if (false === $encoding = mb_detect_encoding($value)) {
            return strlen($value);
        }

        return mb_strwidth($value, $encoding);
    }
}
