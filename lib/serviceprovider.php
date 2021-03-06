<?php
    namespace Octo;

    abstract class ServiceProvider
    {
        protected $app;
        protected $before = true;
        protected $after = false;

    	public function __construct($app = null)
    	{
            $app = !$app ? context('app') : $app;

    		$this->app = $app;
    	}

        abstract public function register();

        public function services() {return [];}
    }
