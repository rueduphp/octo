<?php
    namespace Octo;

    class Locksmith extends Authentication
    {
        protected $ns, $actual, $entity;

        public function __construct($ns = 'web', $actual = 'auth.user', $entity = 'user')
        {
            $this->ns       = $ns;
            $this->actual   = $actual;
            $this->entity   = $entity;
        }

        public function setNs($ns)
        {
            $this->ns = $ns;

            return $this;
        }

        public function setActual($actual)
        {
            $this->actual = $actual;

            return $this;
        }

        public function setEntity($entity)
        {
            $this->entity = $entity;

            return $this;
        }
    }
