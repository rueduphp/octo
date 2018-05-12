<?php
    namespace Octo;

    use function method_exists;

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
         * @var string
         */
        private $messager = CheckingMessages::class;

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
         * @param string $messager
         *
         * @return Checking
         */
        public function setMessager(string $messager): Checking
        {
            $this->messager = $messager;

            return $this;
        }

        /**
         * @return string
         */
        public function getMessager(): string
        {
            return $this->messager;
        }

        /**
         * @param string $field
         * @param callable $callable
         *
         * @throws \ReflectionException
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
         * @throws \ReflectionException
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
         *
         * @throws \ReflectionException
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
         *
         * @throws \ReflectionException
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
         *
         * @throws \ReflectionException
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
         *
         * @throws \ReflectionException
         */
        protected function isRequired(string $field)
        {
            $check = 'octodummy' !== isAke($this->data, $field, 'octodummy');

            if (!$check) {
                $this->addError($field, 'required');
            }
        }

        /**
         * @param string $field
         *
         * @throws \ReflectionException
         */
        protected function isNotEmpty(string $field)
        {
            $value = isAke($this->data, $field, 'octodummy');

            $check = 'octodummy' !== $value && strlen($value) > 0;

            if (!$check) {
                $this->addError($field, 'not_empty');
            }
        }

        /**
         * @param string $field
         *
         * @throws \ReflectionException
         */
        protected function isEmpty(string $field)
        {
            $value = isAke($this->data, $field, 'octodummy');

            $check = 'octodummy' !== $value && strlen($value) === 0;

            if (!$check) {
                $this->addError($field, 'empty');
            }
        }

        /**
         * @param string $field
         *
         * @throws \ReflectionException
         */
        protected function isEmail(string $field)
        {
            $value = isAke($this->data, $field, null);

            $check = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;

            if (!$check) {
                $this->addError($field, 'email');
            }
        }

        /**
         * @param string $field
         *
         * @throws \ReflectionException
         */
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
         *
         * @throws \ReflectionException
         */
        protected function addError(string $field, string $rule)
        {
            if (!isset($this->errors[$field])) {
                $this->errors[$field] = [];
            }

            $this->errors[$field][] = instanciator()->factory($this->messager, $field, $rule, $this->lng);
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
        protected function message()
        {
            $messages = [
                'en' => [
                    'required'  => '%s is required',
                    'empty'     => '%s is not empty',
                    'not_empty' => '%s is empty',
                    'slug'      => '%s is not a valid slug',
                    'email'     => '%s is not a valid email',
                    'integer'   => '%s is not a valid integer',
                    'custom'    => '%s does not match with custom rule',
                    'minlength' => '%s is too short',
                    'maxlength' => '%s is too long',
                ],
                'fr' => [
                    'required'  => '%s est requis',
                    'empty'     => '%s n\'est pas vide',
                    'not_empty' => '%s est vide',
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
