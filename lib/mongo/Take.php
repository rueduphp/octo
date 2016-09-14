<?php
    namespace Octo\Mongo;

    use Octo\File;
    use Octo\Inflector;
    use Octo\Arrays;
    use Octo\Exception;

    class Take
    {
        private $results = [], $db, $table, $orm;

        public function __construct(Db $db)
        {
            $this->db       = $db->db;
            $this->table    = $db->table;
            $this->orm      = $db;
        }

        public function get($object = false)
        {
            return !$object ? $this->results : new Collection($this->orm->makeModels($this->results));
        }

        public function first($object = false)
        {
            return !$object ? current($this->results) : $this->orm->model(current($this->results));
        }

        public function last($object = false)
        {
            return !$object ? end($this->results) : $this->orm->model(end($this->results));
        }

        public function sortBy($field, $sens = 'ASC')
        {
            $collection = lib('collection', [$this->results]);

            if ($sens == 'ASC') {
                $collection = $collection->sortBy($field);
            } else {
                $collection = $collection->sortByDesc($field);
            }

            $this->results = $collection->toArray();

            return $this;
        }

        public function groupBy($field)
        {
            $collection = lib('collection', [$this->results]);

            $collection = $collection->groupBy($field);

            $this->results = $collection->toArray();

            return $this;
        }

        public function limit($limit, $offset = 0)
        {
            $this->resulsts = array_slice($this->resulsts, $offset, $limit);

            return $this;
        }

        public function sum($field)
        {
            $collection = lib('collection', [$this->results]);

            return $collection->sum($field);
        }

        public function min($field, $object = false)
        {
            $collection = lib('collection', [$this->results]);

            $min = $collection->min($field);

            return $object ? $this->orm->model($min) : $min;
        }

        public function max($field, $object = false)
        {
            $collection = lib('collection', [$this->results]);

            $max = $collection->max($field);

            return $object ? $this->orm->model($max) : $max;
        }

        public function avg($field)
        {
            $collection = lib('collection', [$this->results]);

            return (double) $collection->sum($field) / count($this->results);
        }

        public function count()
        {
            return count($this->resulsts);
        }

        /**
         * Flip the items in the collection.
         *
         * @return Take
         */
        public function flip()
        {
            $this->resulsts = array_flip($this->resulsts);

            return $this;
        }

        public function take($what, $where = null, $object = false)
        {
             $collection = [];

            if (is_string($what)) {
                if (fnmatch('*,*', $what)) {
                    $what = str_replace(' ', '', $what);
                    $what = explode(',', $what);
                } else {
                    $what = [$what];
                }

                $res = empty($this->results) ? $this->orm->get($object) : $this->results;

                foreach ($res as $r) {
                    if (is_object($r)) {
                        $row = $r->assoc();
                    } else {
                        $row = $r;
                    }

                    foreach ($what as $fk) {
                        if (!fnmatch('*s', $fk)) {
                            $value = isAke($row, $fk . '_id', false);

                            if (false !== $value) {
                                $query = rdb($this->db, $fk)->where(['id', '=', (int) $value]);

                                if (!empty($where) && is_array($where)) {
                                    $first = current($where);

                                    if (is_string($first)) {
                                        $query = $query->where($where);
                                    } else {
                                        $query = $query->multiQuery($where);
                                    }
                                }

                                $obj = $query->first($object);

                                if ($obj) {
                                    if (is_object($r)) {
                                        $r->$fk = $obj;
                                    } else {
                                        $row[$fk] = $obj;
                                    }

                                    $collection[] = is_object($r) ? $r : $row;
                                }
                            }
                        } else {
                            $query = rdb($this->db, substr($fk, 0, -1))
                            ->where([$this->table . '_id', '=', (int) $row['id']]);

                            if (!empty($where) && is_array($where)) {
                                $first = current($where);

                                if (is_string($first)) {
                                    $query = $query->where($where);
                                } else {
                                    $query = $query->multiQuery($where);
                                }
                            }

                            $objs = $query->get($object);

                            if (!empty($objs)) {
                                if (is_object($r)) {
                                    $r->$fk = $objs;
                                } else {
                                    $row[$fk] = $objs;
                                }

                                $collection[] = is_object($r) ? $r : $row;
                            }
                        }
                    }
                }
            }

            $this->results = $collection;

            return $this;
        }
    }
