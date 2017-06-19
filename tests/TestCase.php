<?php
    abstract class TestCase extends Octo\TestCase
    {
        protected $baseUrl = 'http://localhost';

        public function makeApplication()
        {
            return Octo\context('app');
        }
    }
