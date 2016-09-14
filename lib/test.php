<?php
    namespace Octo;

    class Test
    {
        private $bad    = 0;
        private $good   = 0;
        private $errors = [];

        public function isTrue($assert)
        {
            return $this->check(true === $assert);
        }

        public function isFalse($assert)
        {
            return $this->check(false === $assert);
        }

        public function isNotFalse($boolean)
        {
            return $this->check((false === $boolean) ? false : true);
        }

        public function isNull($variable)
        {
            return $this->check(is_null($variable));
        }

        public function isNotNull($variable)
        {
            return $this->check(!is_null($variable));
        }

        public function isEmpty($variable)
        {
            return $this->check(empty($variable));
        }

        public function isNotEmpty($variable)
        {
            return $this->check(empty($variable) ? false : true);
        }

        public function isIndexExists($array, $key)
        {
            $checkArray = $this->isArray($array);

            if (false === $checkArray) {
                return $this->check(false);
            }

            return $this->check(array_key_exists($key, $array));
        }

        public function isScalar($variable)
        {
            return $this->check(is_scalar($variable));
        }

        public function isArray($variable)
        {
            return $this->check(is_array($variable) || $variable instanceof \ArrayAccess);
        }

        public function isNotEmptyArray(&$variable)
        {
            $checkArray = $this->isArray($variable);

            if (false === $checkArray) {
                return $this->check(false);
            }

            return $this->check((!$variable) ? false : true);
        }

        public function isInteger($variable)
        {
            return $this->check((!(is_numeric($variable) && $variable == (int) $variable)) ? false : true);
        }

        public function isPositiveInteger($variable)
        {
            $checkInteger = $this->checkInteger($variable);

            if (false === $checkInteger) {
                return $this->check(false);
            }

            return $this->check(0 <= $variable);
        }

        public function isFloat($variable)
        {
            return $this->check($this->checkFloat($variable));
        }

        public function isString($variable)
        {
            return $this->check(is_string($variable));
        }

        public function isBoolean($variable)
        {
            return $this->check($variable === true || $variable === false);
        }

        public function isTernaryBase($variable)
        {
            return $this->check(($variable === true) || ($variable === false) || ($variable === null));
        }

        public function areBrothers($first, $second)
        {
            return $this->check((get_class($first) === get_class($second)));
        }

        public function isEqual($first, $second)
        {
            return $this->check($first == $second);
        }

        public function isNotEqual($first, $second)
        {
            return $this->check($first != $second);
        }

        public function isSame($first, $second)
        {
            return $this->check($first === $second);
        }

        public function isStrict($first, $second)
        {
            return $this->check($first === $second);
        }

        public function isNotSame($first, $second)
        {
            return $this->check($first !== $second);
        }

        public function isTypelessEqual($first, $second)
        {
            return $this->check($first == $second);
        }

        public function isLesser($first, $second)
        {
            return $this->check($first < $second);
        }

        public function isGreater($first, $second)
        {
            return $this->check($first > $second);
        }

        public function isLesserOrEqual($first, $second)
        {
            return $this->check($first <= $second);
        }

        public function isGreaterOrEqual($first, $second)
        {
            return $this->check($first >= $second);
        }

        public function isInstance($first, $second)
        {
            return $this->check($first instanceof $second);
        }

        public function classExists($className)
        {
            return $this->check(class_exists($className, true));
        }

        public function methodExists($object, $method)
        {
            return $this->check(method_exists($object, $method));
        }

        public function isObject($object)
        {
            return $this->check(is_object($object));
        }

        public function checkInteger($value)
        {
            return $this->check(
                is_numeric($value) && ($value == (int) $value) && (strlen($value) == strlen((int) $value))
            );
        }

        public function checkFloat($value)
        {
            return $this->check(is_numeric($value) && ($value == (float) $value));
        }

        public function checkScalar($value)
        {
            return $this->check(is_scalar($value));
        }

        public function checkContain($needle, $chain)
        {
            $needle = Inflector::lower($needle);
            $chain  = Inflector::lower($chain);

            return $this->check(fnmatch('*' . $needle . '*', $chain));
        }

        public function isUrl($url)
        {
            return $this->check(filter_var($url, FILTER_VALIDATE_URL) !== false);
        }

        public function isEmail($email)
        {
            return $this->check(filter_var($email, FILTER_VALIDATE_EMAIL) !== false);
        }

        public function getGood()
        {
            return $this->good;
        }

        public function getBad()
        {
            return $this->bad;
        }

        public function getErrors()
        {
            return $this->errors;
        }

        private function check($check)
        {
            if ($check) {
                $this->good++;
            } else {
                $bt = debug_backtrace(false);
                $test = $bt[3];
                $file = $test['file'];
                $line = $test['line'];

                $ct = file($file);
                $code = $ct[$line - 1];

                $this->errors[] = [
                    'test'  => $bt[5]['function'],
                    'error' => $bt[4]['function'] . ' ' . implode(', ', $bt[4]['args']),
                    'file'  => $file,
                    'line'  => $line,
                    'code'  => trim($code)
                ];

                $this->bad++;
            }
        }
    }
