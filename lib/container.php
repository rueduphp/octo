<?php
    namespace Octo;

    class Container extends Ghost
    {
        protected static function called()
        {
            return actual('container.class', maker(get_called_class()));
        }

        public function __construct(array $values = [])
        {
            parent::__construct($values, 'container');
        }

        public function build($class, $args = [], $single = true)
        {
            if (!isset($this->$class) || !$single) {
                $this->$class = maker($class, $args);
            }

            return $this->$class;
        }

        public static function __callStatic($m, $a)
        {
            $i = static::called();

            return call_user_func_array([$i, $m], $a);
        }
    }
