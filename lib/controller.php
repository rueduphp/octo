<?php
    namespace Octo;

    class Controller
    {
        public function __set($var, $what)
        {
            $this->$var = $what;
        }

        public function __isset($var)
        {
            return isset($this->$var);
        }

        public function __get($var)
        {
            return isset($this->$var) ? $this->$var : null;
        }

        public function __call($method, $args)
        {
            if (isset($this->$method)) {
                if (is_callable($this->$method)) {
                    return call_user_func_array($this->$method, $args);
                }
            }
        }
    }
