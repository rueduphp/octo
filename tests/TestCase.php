<?php
    abstract class TestCase extends Octo\TestCase
    {
        protected $baseUrl = 'http://localhost';

        public function makeApplication()
        {
            Octo\Config::set('octalia.engine', 'rdb');

            return Octo\context('app');
        }
    }
