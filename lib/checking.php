<?php
    namespace Octo;

    use function sprintf;

    class Checking
    {
        /**
         * @var array
         */
        protected $data     = [];

        /**
         * @var array
         */
        protected $rules    = [];

        /**
         * @var array
         */
        protected $errors   = [];

        /**
         * @var string
         */
        private $lng;

        public function __construct($data = null, $lng = 'en')
        {
            $this->data = empty($data) ? $_POST : $data;
            $this->lng = $lng;
        }

        public function success()
        {
            return empty($this->errors);
        }

        public function fail()
        {
            return !empty($this->errors);
        }

        /**
         * @param string $field
         *
         * @return Fluent
         */
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

        /**
         * @return string
         */
        public function getLng(): string
        {
            return $this->lng;
        }

        /**
         * @param string $lng
         */
        public function setLng(string $lng)
        {
            $this->lng = $lng;
        }

        /**
         * @return array
         */
        public function getErrors(): array
        {
            return $this->errors;
        }

        private function isCustom($field, $callable)
        {
            $value = isAke($this->data, $field, null);

            $check = call_user_func_array($callable, [$field, $value]);

            if (!$check) {
                $this->addError($field, 'custom');
            }
        }

        private function isMinLength($field, $length)
        {
            $value = isAke($this->data, $field, null);
            $check = mb_strlen($value) >= $length;

            if (!$check) {
                $this->addError($field, 'minlength');
            }
        }

        private function isMaxLength($field, $length)
        {
            $value = isAke($this->data, $field, null);
            $check = mb_strlen($value) <= $length;

            if (!$check) {
                $this->addError($field, 'maxlength');
            }
        }

        private function isInteger($field)
        {
            $value = isAke($this->data, $field, null);
            $check = reallyInt($value);

            if (!$check) {
                $this->addError($field, 'integer');
            }
        }

        private function isInt($field)
        {
            $value = isAke($this->data, $field, null);
            $check = reallyInt($value);

            if (!$check) {
                $this->addError($field, 'integer');
            }
        }

        private function isRequired($field)
        {
            $value = isAke($this->data, $field, null);

            $check = $value && strlen($value);

            if (!$check) {
                $this->addError($field, 'required');
            }
        }

        private function isEmail($field)
        {
            $value = isAke($this->data, $field, null);

            $check = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;

            if (!$check) {
                $this->addError($field, 'email');
            }
        }

        private function isSlug($field)
        {
            $value = isAke($this->data, $field, null);

        }

        protected function addError($field, $rule)
        {
            if (!isset($this->errors[$field])) {
                $this->errors[$field] = [];
            }

            $this->errors[$field][] = new CheckingMessages($field, $rule, $this->lng);
        }
    }

    class CheckingMessages
    {
        /**
         * @var string
         */
        private $field;

        /**
         * @var string
         */
        private $lng;

        /**
         * @var string
         */
        private $rule;

        /**
         * @param string $field
         * @param string $rule
         * @param string $lng
         */
        public function __construct($field, $rule, $lng = 'en')
        {
            $this->field    = $field;
            $this->rule     = $rule;
            $this->lng      = $lng;
        }

        /**
         * @return string
         */
        public function __toString()
        {
            return $this->get();
        }

        /**
         * @return string
         */
        public function get()
        {
            return sprintf($this->message(), $this->field);
        }

        /**
         * @return string
         */
        private function message()
        {
            $messages = [
                'en' => [
                    'required'  => '%s is required',
                    'slug'      => '%s is not a valid slug',
                    'email'     => '%s is not a valid email',
                    'integer'   => '%s is not a valid integer',
                    'custom'    => '%s does not match with custom rule',
                    'minlength' => '%s is too short',
                    'maxlength' => '%s is too long',
                ]
            ];

            $key = $this->lng . '.' . $this->rule;

            return aget($messages, $key, '');
        }
    }
