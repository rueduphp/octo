<?php
    namespace Octo;

    class Dictionary
    {
        private $data = [], $segment = [];

        public function __construct($file)
        {
            $this->data = include $file;
        }

        public function hasSegment($k)
        {
            $segment = isAke($this->data, $k, []);

            return !empty($segment);
        }

        public function getSegment($k)
        {
            $this->segment = isAke($this->data, $k, []);

            return $this;
        }

        public function get($k, $d = null)
        {
            return isAke($this->segment, $k, $d);
        }

        public function has($k)
        {
            return 'octodummy' != isAke($this->segment, $k, 'octodummy');
        }
    }
