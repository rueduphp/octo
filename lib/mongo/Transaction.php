<?php
    namespace Octo\Mongo;

    use Octo\File;
    use Octo\Inflector;

    class Transaction
    {
        private $from, $to;

        public function __construct(Db $from)
        {
            $this->from = $from;
            $this->to = Db::instance('transaction', $from->db . '_' . $from->table . '_' . time());

            $this->populate();
        }

        public function __destruct()
        {
            /* On efface le model de la base tampon et on vide la base */
            $modelFile = APPLICATION_PATH . DS . 'models' . DS . 'Bigdata' . DS . 'models' . DS . Inflector::lower($this->to->db) . DS . ucfirst(Inflector::lower($this->to->table)) . '.php';

            File::delete($modelFile);

            $this->to->drop();
        }

        private function populate()
        {
            $data = $this->from->where(['id', '>', 0])->exec();

            if (!empty($data)) {
                foreach ($data as $row) {
                    $this->to->create($row)->save();
                }
            }
        }

        public function __call($method, $args)
        {
            return call_user_func_array([$this->to, $method], $args);
        }

        public function commit()
        {
            $this->from = $this->from->drop();

            $dataTo = $this->to->where(['id', '>', 0])->exec();

            if (!empty($data)) {
                foreach ($data as $row) {
                    $this->from->create($row)->save();
                }
            }

            return $this->from;
        }

        public function rollback()
        {
            return $this->from;
        }
    }
