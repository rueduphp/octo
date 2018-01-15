<?php
    namespace Octo;

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

        /**
         * @param array|null $data
         * @param string $lng
         */
        public function __construct(?array $data = null, string $lng = 'en')
        {
            $this->data = empty($data) ? $_POST : $data;
            $this->lng = $lng;
        }

        /**
         * @return bool
         */
        public function success()
        {
            return empty($this->errors);
        }

        /**
         * @return bool
         */
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

        /**
         * @param string $field
         * @param callable $callable
         */
        protected function isCustom(string $field, callable $callable)
        {
            $value = isAke($this->data, $field, null);

            $check = call_user_func_array($callable, [$field, $value]);

            if (!$check) {
                $this->addError($field, 'custom');
            }
        }

        /**
         * @param string $field
         * @param int $length
         */
        protected function isMinLength(string $field, int $length)
        {
            $value = isAke($this->data, $field, null);
            $check = mb_strlen($value) >= $length;

            if (!$check) {
                $this->addError($field, 'minlength');
            }
        }

        /**
         * @param string $field
         * @param int $length
         */
        protected function isMaxLength(string $field, int $length)
        {
            $value = isAke($this->data, $field, null);
            $check = mb_strlen($value) <= $length;

            if (!$check) {
                $this->addError($field, 'maxlength');
            }
        }

        /**
         * @param string $field
         */
        protected function isInteger(string $field)
        {
            $value = isAke($this->data, $field, null);
            $check = reallyInt($value);

            if (!$check) {
                $this->addError($field, 'integer');
            }
        }

        /**
         * @param string $field
         */
        protected function isInt(string $field)
        {
            $value = isAke($this->data, $field, null);
            $check = reallyInt($value);

            if (!$check) {
                $this->addError($field, 'integer');
            }
        }

        /**
         * @param string $field
         */
        protected function isRequired(string $field)
        {
            $value = isAke($this->data, $field, null);

            $check = $value && strlen($value);

            if (!$check) {
                $this->addError($field, 'required');
            }
        }

        /**
         * @param string $field
         */
        protected function isEmail(string $field)
        {
            $value = isAke($this->data, $field, null);

            $check = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;

            if (!$check) {
                $this->addError($field, 'email');
            }
        }

        protected function isSlug(string $field)
        {
            $value = isAke($this->data, $field, null);
            $pattern = "/^[a-z]+(?:-[a-z]+)*$/";

            $check = 0 !== preg_match($pattern, $value);

            if (!$check) {
                $this->addError($field, 'slug');
            }
        }

        /**
         * @param string $field
         * @param string $rule
         */
        protected function addError(string $field, string $rule)
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
                ],
                'fr' => [
                    'required'  => '%s est requis',
                    'slug'      => '%s n\'est pas un slug valide',
                    'email'     => '%s n\'est pas un email valide',
                    'integer'   => '%s n\'est pas un nombre entier valide',
                    'custom'    => '%s ne correspond pas à la règle personnalisée',
                    'minlength' => '%s est trop court',
                    'maxlength' => '%s is trop long',
                ]
            ];

            $key = $this->lng . '.' . $this->rule;

            return aget($messages, $key, '');
        }
    }
