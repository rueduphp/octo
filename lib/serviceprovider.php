<?php
    namespace Octo;

    abstract class ServiceProvider
    {
        protected $app;
        protected $before = true;
        protected $after = false;

    	public function __construct($app = null)
    	{
            $app = !$app ? app() : $app;

    		$this->app = $app;
    	}

    	public function before() {}

    	abstract public function register();

        public function services() {return [];}

        public function after() {}
    }
