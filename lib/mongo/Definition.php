<?php
    namespace Octo\Mongo;

    class Definition extends Base
    {
        private $output;

        public function __construct($class, Output $output)
        {
            parent::__construct($class);

            $this->setOutput($output);
        }

        public function setOutput(Output $output)
        {
            $this->output = $output;
        }

        public function getOutput()
        {
            return $this->output;
        }
    }
