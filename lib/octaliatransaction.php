<?php
    namespace Octo;

   class OctaliaTransaction
    {
        private $instance, $db, $callback;

        public function __construct(Octalia $db, callable $callback)
        {
            $this->db       = $db;
            $this->callback = $callback;
        }

        public function begin()
        {
            $this->instance = $this->db->copy('transactions.' . str_replace('.', $this->db->path));

            call_user_func_array(
                $this->callback, [
                    $this->instance,
                    $this
                ]
            );
        }

        public function commit()
        {
            $this->db->drop();
            $this->instance->copy($this->db->db . '.' . $this->db->table);
            $this->instance->drop();

            return $this->db;
        }

        public function rollback()
        {
            $this->instance->drop();

            return $this->db;
        }

        public function success()
        {
            return $this->commit();
        }

        public function fail()
        {
            return $this->rollback();
        }
    }
