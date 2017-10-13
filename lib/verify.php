<?php
    namespace Octo;

    class Verify
    {
        protected $data;
        protected $errors = [];

        public function __construct(array $data)
        {
            $this->data = $data;
        }

        public function getErrors()
        {
            return $this->errors;
        }

        public function success()
        {
            return empty($this->errors);
        }

        public function fail()
        {
            return !empty($this->errors);
        }

        public function required(string ...$keys)
        {
            foreach ($keys as $key) {
                if ('octodummy' === $this->getValue($key)) {
                    $this->errors[$key] = "The field $key is required.";
                }
            }

            return $this;
        }

        public function custom()
        {
            $keys = func_get_args();

            $callable = array_shift($keys);

            foreach ($keys as $key) {
                $check = call_user_func_array($callable, [$this->getValue($key)]);

                if (false === $check) {
                    $this->errors[$key] = "The field $key is invalid.";
                }
            }

            return $this;
        }

        public function email(string ...$keys)
        {
            foreach ($keys as $key) {
                if (false === $this->isEmail($this->getValue($key))) {
                    $this->errors[$key] = "The field $key is not a valid email.";
                }
            }

            return $this;
        }

        private function isEmail($value)
        {
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        }

        public function datetime(string ...$keys)
        {
            foreach ($keys as $key) {
                $value  = $this->getValue($key);

                if ('octodummy' === $value) {
                    $this->errors[$key] = "The field $key is empty.";
                } else {
                    $time   = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
                    $errors = \DateTime::getLastErrors();

                    if ($errors['error_count'] > 0 || $errors['warning_count'] > 0 || false === $time) {
                        $this->errors[$key] = "The field $key is not a valid datetime.";
                    }
                }
            }

            return $this;
        }

        public function slug(string ...$keys)
        {
            $pattern = '/^([a-z0-9]+-?)+$/';

            return $this->pattern($keys, $pattern);
        }

        public function regex($key, $pattern)
        {
            $keys = [$key];

            return $this->pattern($keys, $pattern);
        }

        public function length($key, $min = null, $max = null)
        {
            $error = false;

            $value = $this->getValue($key);

            if ('octodummy' === $value) {
                $this->errors[$key] = "The field $key is empty.";
            } else {
                $length = mb_strlen($value);

                if (!is_null($min) && $length < $min) {
                    $this->errors[$key] = "The field $key has $length length.";
                    $error = true;
                }

                if ($max && !$error && $length > $max) {
                    $this->errors[$key] = "The field $key has $length length.";
                }
            }

            return $this;
        }

        public function notEmpty(string ...$keys)
        {
            foreach ($keys as $key) {
                $value = $this->getValue($key);

                if ('octodummy' !== $value) {
                    if (mb_strlen($value) == 0) {
                        $this->errors[$key] = "The field $key is empty.";
                    }
                } else {
                    $this->errors[$key] = "The field $key is null.";
                }
            }

            return $this;
        }

        protected function pattern($keys, $pattern)
        {
            foreach ($keys as $key) {
                $value = $this->getValue($key);

                if ('octodummy' !== $value) {
                    if (!preg_match($pattern, $value)) {
                        $this->errors[$key] = "The field $key is not valid.";
                    }
                }
            }

            return $this;
        }

        protected function getValue($key)
        {
            return isAke($this->data, $key, 'octodummy');
        }

        public function __call($m, $a)
        {
            $assert = maker(Assert::class);
            $key    = array_shift($a);
            $value  = $this->getValue($key);

            try {
                call_user_func_array([$assert, $m], [$value]);
            } catch (\InvalidArgumentException $e) {
                $this->errors[$key] = $e->getMessage();
            }

            return $this;
        }
    }
