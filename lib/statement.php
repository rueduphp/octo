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
    }
