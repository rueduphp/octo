<?php
    namespace Octo;

    class Statement extends \PDOStatement
    {
        protected $pdo;

        protected function __construct(\PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        public function reveal()
        {
            return $this->pdo;
        }

        public function __call($m, $a)
        {
            $method = '\\Octo\\' . $m;

            if (function_exists($method)) {
                return call_user_func_array($method, $a);
            }

            return $this;
        }
    }
