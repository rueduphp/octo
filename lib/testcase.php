<?php
    namespace Octo;

    use PHPUnit\Framework\TestCase as PTC;

    abstract class TestCase extends PTC
    {
        protected $app;

        abstract public function makeApplication();

        public function em($entity, $new = false)
        {
            return dbMemory($entity, $new);
        }

        protected function setUp()
        {
            if (!$this->app) {
                $this->refreshApplication();
            }
        }

        protected function refreshApplication()
        {
            putenv('APPLICATION_ENV=testing');

            $this->app = $this->makeApplication();
        }

        protected function tearDown()
        {
            if ($this->app) {
                $this->app = null;
            }

            if (property_exists($this, 'serverVariables')) {
                $this->serverVariables = [];
            }
        }
    }
