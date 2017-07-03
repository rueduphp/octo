<?php
    namespace Octo;

    class Checker
    {
        private $bag        = [];
        private $errors     = [];

        public function __construct(array $data = null)
        {
            $data = empty($data) ? $_POST : $data;

            $this->bag = Input::clean($data);
        }

        public function fresh()
        {
            $this->errors = [];

            return $this;
        }

        public function required($field, $message = '##field## is required')
        {
            $check = strlen(isAke($this->bag, $field, null)) > 0;

            if (!$check) {
                $this->errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public function min($field, $min, $message = '##field## is too short')
        {
            $check = strlen(isAke($this->bag, $field, null)) < $min + 0;

            if (!$check) {
                $this->errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public function max($field, $max, $message = '##field## is too long')
        {
            $check = strlen(isAke($this->bag, $field, null)) > $max + 0;

            if (!$check) {
                $this->errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public function fnmatch($field, $fnmatch, $message = '##field## is incorrect')
        {
            $check = fnmatch($fnmatch, isAke($this->bag, $field, null));

            if (!$check) {
                $this->errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public function match($field, $pattern, $message = '##field## is incorrect')
        {
            $check = false;

            if (preg_match_all('#^' . trim($pattern) . '$#', isAke($this->bag, $field, null), $matches, PREG_OFFSET_CAPTURE)) {
                $check = true;
            }

            if (!$check) {
                $this->errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public function int($field, $message = '##field## is not an integer')
        {
            $check = reallyInt(isAke($this->bag, $field, null));

            if (!$check) {
                $this->errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public function float($field, $message = '##field## is not a float')
        {
            $check = isAke($this->bag, $field, null) === floatval(isAke($this->bag, $field, null));

            if (!$check) {
                $this->errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public function number($field, $message = '##field## is not a number')
        {
            $check = isAke($this->bag, $field, null) === isAke($this->bag, $field, null) + 0;

            if (!$check) {
                $this->errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public function numeric($field, $message = '##field## is not numeric')
        {
            $check = is_numeric(isAke($this->bag, $field, null));

            if (!$check) {
                $this->errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public function email($field, $message = '##field## is not an email')
        {
            $check = filter_var(isAke($this->bag, $field, null), FILTER_VALIDATE_EMAIL);

            if (!$check) {
                $this->errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public function bool($field, $message = '##field## is not a boolean')
        {
            $check = isAke($this->bag, $field, null) === (bool) isAke($this->bag, $field, null);

            if (!$check) {
                $this->errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public function custom($field, callable $custom, $message = '##field## is incorrect')
        {
            $check = call($custom, [isAke($this->bag, $field, null)]);

            if (!$check) {
                $this->errors[] = str_replace('##field##', $field, $message);
            }

            return $check;
        }

        public function success()
        {
            return empty($this->errors);
        }

        public function fails()
        {
            return !empty($this->errors);
        }

        public function errors()
        {
            return $this->errors;
        }

        public static function __call($m, $a)
        {
            $field      = array_shift($a);
            $value      = isAke($this->bag, $field, null);

            $args       = array_merge([$value], $a);

            try {
                call_user_func_array(['Octo\\Assert', $m], $args);
            } catch (\Exception $e) {
                $this->errors[] = str_replace('##field##', $field, $e->getMessage());
            }
        }
    }
