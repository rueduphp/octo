<?php
    namespace Octo;

    class Redirect
    {
        protected $url;

        public function __construct($url)
        {
            $this->url = $this->url('/' . $url);
        }

        public function with(array $data)
        {
            session('with')->setData($data);

            return $this;
        }

        public function go()
        {
            header("Location: " . $this->url);

            exit;
        }

        public function flash($k, $v = null)
        {
            session('flash')->set($k, $v);

            return $this;
        }

        protected function url($url = '/')
        {
            return Registry::get('octo.subdir', '') . $url;
        }
    }
