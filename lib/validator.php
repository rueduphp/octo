<?php
    namespace Octo;

    class Validator
    {
        private $rules = [];
        private static $steps = [];

        public function __construct(array $rules)
        {
            $this->rules = $rules;
        }

        public function check(array $data = [])
        {
            if (empty($data)) {
                $data = empty($_POST) ? $_GET : $_POST;
            }

            $errors = [];

            foreach ($data as $k => $v) {
                $closure = isAke($this->rules, $k, null);

                if ($closure) {
                    if (is_callable($closure)) {
                        $check = $closure($v);

                        if (true !== $check) {
                            $errors[] = $check;
                        }
                    }
                }
            }

            return empty($errors) ? true : $errors;
        }

        public static function set($k, callable $check)
        {
            self::$steps[$k] = $cb;
        }

        public static function run($k, $field, $data = null)
        {
            $closure = isAke(self::$steps, $k, null);

            if ($closure) {
                if (!$data) {
                    $data = empty($_POST) ? $_GET : $_POST;
                }

                if (is_callable($closure)) {
                    return $closure($field, isAke($data, $field, null), $data);
                }
            }

            return false;
        }
    }
