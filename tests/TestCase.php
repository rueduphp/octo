<?php
    use function Octo\instanciator;

    abstract class TestCase extends Octo\TestCase
    {
        protected $baseUrl = 'http://localhost';

        public function setUp()
        {
            parent::setUp();
            Octo\Dir::rmdir(__DIR__ . '/cache');
            Octo\Dir::mkdir(__DIR__ . '/cache');
        }

        /**
         * @return \Octo\Instanciator
         */
        public function making()
        {
            return instanciator();
        }

        /**
         * @return \Octo\Context
         */
        public function makeApplication()
        {
            Octo\Config::set('octalia.engine', 'rdb');
            Octo\Config::set('dir.cache', __DIR__ . '/cache');
            Octo\Config::set('fmr.instance', new Octo\Now('testcache'));
            Octo\Config::set('DATABASE_DRIVER', 'sqlite');

            return Octo\context('app');
        }

        public function __invoke()
        {
            return get_called_class();
        }
    }
