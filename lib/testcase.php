<?php
    namespace Octo;

    use Mockery;
    use PHPUnit_Framework_TestCase;

    abstract class TestCase extends PHPUnit_Framework_TestCase
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

            if (class_exists('Mockery')) {
                Mockery::close();
            }
        }
    }
