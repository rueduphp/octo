<?php
    namespace Octo;

    class Checking
    {
        protected $data     = [];
        protected $rules    = [];
        protected $errors   = [];

        public function __construct($data = null)
        {
            $this->data = empty($data) ? $_POST : $data;
        }

        public function success()
        {
            return empty($this->errors);
        }

        public function fail()
        {
            return !empty($this->errors);
        }

        public function add($field)
        {
            $rule = new Fluent;
            $this->rules[$field] = $rule;

            return $rule;
        }

        public function validate()
        {
            foreach ($this->rules as $field => $rule) {
                $checks = $rule->getAttributes();

                foreach ($checks as $check => $args) {
                    $method = lcfirst(Strings::camelize('is_' . $check));
                    $this->$method($field, $args);
                }
            }
        }

        private function isCustom($field, $callable)
        {
            $value = isAke($this->data, $field, null);

            $check = call_user_func_array($callable, [$field, $value]);

            if (!$check) {
                $this->addError($field, "$field does not match with custom rule.");
            }
        }

        private function isMinLength($field, $length)
        {
            $value = isAke($this->data, $field, null);
            $check = mb_strlen($value) >= $length;

            if (!$check) {
                $this->addError($field, "$field is too short.");
            }
        }

        private function isMaxLength($field, $length)
        {
            $value = isAke($this->data, $field, null);
            $check = mb_strlen($value) <= $length;

            if (!$check) {
                $this->addError($field, "$field is too long.");
            }
        }

        private function isInteger($field)
        {
            $value = isAke($this->data, $field, null);
            $check = reallyInt($value);

            if (!$check) {
                $this->addError($field, "$field is not an integer.");
            }
        }

        private function isInt($field)
        {
            $value = isAke($this->data, $field, null);
            $check = reallyInt($value);

            if (!$check) {
                $this->addError($field, "$field is not an integer.");
            }
        }

        private function isRequired($field)
        {
            $check = isset($this->data[$field]) && strlen($this->data[$field]);

            if (!$check) {
                $this->addError($field, "$field is required but empty.");
            }
        }

        private function isEmail($field)
        {
            $value = isAke($this->data, $field, null);

            $check = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;

            if (!$check) {
                $this->addError($field, "$field is not an email.");
            }
        }

        protected function addError($field, $message)
        {
            if (!isset($this->errors[$field])) {
                $this->errors[$field] = [];
            }

            $this->errors[$field][] = $message;
        }
    }
