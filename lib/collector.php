<?php
    namespace Octo;

    class Collector
    {
        private static $data = [];
        private $ns;

        public function __construct($ns = 'core', array $rows = [])
        {
            $this->ns = $ns;
            self::$data[$ns] = $rows;
        }

        public function push($row)
        {
            self::$data[$this->ns][] = $row;

            return $this;
        }

        public function pull()
        {
            $row = array_pop(self::$data[$this->ns]);

            return $row;
        }

        public function last()
        {
            $row = end(self::$data[$this->ns]);

            return $row;
        }

        public function first()
        {
            $row = current(self::$data[$this->ns]);

            return $row;
        }

        public function count()
        {
            return count(self::$data[$this->ns]);
        }

        public function row($index, $default = null)
        {
            return isset(self::$data[$this->ns][$index]) ? self::$data[$this->ns][$index] : $default;
        }

        public function unrow($index, $default = null)
        {
            if (isset(self::$data[$this->ns][$index])) {
                $value = self::$data[$this->ns][$index];

                unset(self::$data[$this->ns][$index]);

                return $value;
            }

            return $default;
        }

        public function collection()
        {
            return coll(self::$data[$this->ns]);
        }

        public function toArray()
        {
            return self::$data[$this->ns];
        }

        public function toJson()
        {
            return json_encode(self::$data[$this->ns]);
        }

        public function pattern($pattern = '*')
        {
            return Arrays::pattern(self::$data[$this->ns], $pattern);
        }
    }
