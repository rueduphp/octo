<?php
    namespace Octo;

    class Hooks
    {
        private $object;

        public function __construct(Object $object)
        {
            $this->object = $object;
        }

        public function set($when, callable $cb)
        {
            Arrays::set($this->object->hooks, $when, $cb);

            return $this;
        }

        public function fire($when)
        {
            $cb = Arrays::get($this->object->hooks, $when, null);

            if (is_callable($cb)) {
                $table      = isAke($this->object->callbacks, "table", null);
                $database   = isAke($this->object->callbacks, "db", null);

                $db = null;

                if ($table && $database) {
                    if (is_callable($table) && is_callable($database)) {
                        $db = engine($this->object->db(), $this->object->table());
                    }
                }

                return call_user_func_array($cb, [$this->object, $db]);
            }

            return $this->object;
        }
    }
