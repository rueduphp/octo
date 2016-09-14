<?php
    namespace Octo;

    use Iterator;
    use Countable;
    use Serializable;
    use SplPriorityQueue as PQ;

    class Pile implements Iterator, Countable, Serializable
    {
        const EXTR_DATA             = PQ::EXTR_DATA;
        const EXTR_PRIORITY         = PQ::EXTR_PRIORITY;
        const EXTR_BOTH             = PQ::EXTR_BOTH;

        protected $extractFlag      = self::EXTR_DATA;
        protected $values           = [];
        protected $priorities       = [];
        protected $subPriorities    = [];
        protected $maxPriority      = 0;
        protected $count            = 0;
        protected $index            = 0;
        protected $subIndex         = 0;

        public function insert($value, $priority = 1)
        {
            if (!is_int($priority)) {
                throw new Exception('The priority must be an integer');
            }

            $this->values[$priority][] = $value;

            if (!isset($this->priorities[$priority])) {
                $this->priorities[$priority] = $priority;

                $this->maxPriority = max(
                    $priority,
                    $this->maxPriority
                );
            }

            ++$this->count;
        }

        public function extract()
        {
            if (!$this->valid()) {
                return false;
            }

            $value = $this->current();

            $this->nextAndRemove();

            return $value;
        }

        public function remove($datum)
        {
            $this->rewind();

            while ($this->valid()) {
                if (current($this->values[$this->maxPriority]) === $datum) {
                    $index = key($this->values[$this->maxPriority]);

                    unset($this->values[$this->maxPriority][$index]);

                    --$this->count;

                    return true;
                }

                $this->next();
            }

            return false;
        }

        public function count()
        {
            return $this->count;
        }

        public function current()
        {
            switch ($this->extractFlag) {
                case self::EXTR_DATA:
                    return current($this->values[$this->maxPriority]);
                case self::EXTR_PRIORITY:
                    return $this->maxPriority;
                case self::EXTR_BOTH:
                    return [
                        'data'     => current($this->values[$this->maxPriority]),
                        'priority' => $this->maxPriority
                    ];
            }
        }

        public function key()
        {
            return $this->index;
        }

        protected function nextAndRemove()
        {
            if (false === next($this->values[$this->maxPriority])) {
                unset($this->priorities[$this->maxPriority]);
                unset($this->values[$this->maxPriority]);
                $this->maxPriority = empty($this->priorities) ? 0 : max($this->priorities);
                $this->subIndex    = -1;
            }

            ++$this->index;
            ++$this->subIndex;
            --$this->count;
        }

        public function next()
        {
            if (false === next($this->values[$this->maxPriority])) {
                unset($this->subPriorities[$this->maxPriority]);
                reset($this->values[$this->maxPriority]);
                $this->maxPriority = empty($this->subPriorities) ? 0 : max($this->subPriorities);
                $this->subIndex    = -1;
            }

            ++$this->index;
            ++$this->subIndex;
        }

        public function valid()
        {
            return isset($this->values[$this->maxPriority]);
        }

        public function rewind()
        {
            $this->subPriorities = $this->priorities;
            $this->maxPriority   = empty($this->priorities) ? 0 : max($this->priorities);
            $this->index         = 0;
            $this->subIndex      = 0;
        }

        public function toArray()
        {
            $array = [];

            foreach (clone $this as $item) {
                $array[] = $item;
            }

            return $array;
        }

        public function serialize()
        {
            $clone = clone $this;
            $clone->setExtractFlags(self::EXTR_BOTH);

            $data = [];

            foreach ($clone as $item) {
                $data[] = $item;
            }

            return serialize($data);
        }

        public function unserialize($data)
        {
            foreach (unserialize($data) as $item) {
                $this->insert($item['data'], $item['priority']);
            }
        }

        public function setExtractFlags($flag)
        {
            switch ($flag) {
                case self::EXTR_DATA:
                case self::EXTR_PRIORITY:
                case self::EXTR_BOTH:
                    $this->extractFlag = $flag;
                    break;
                default:
                    throw new Exception("The extract flag specified is not valid");
            }
        }

        public function isEmpty()
        {
            return empty($this->values);
        }

        public function contains($datum)
        {
            foreach ($this->values as $values) {
                if (in_array($datum, $values)) {
                    return true;
                }
            }

            return false;
        }

        public function hasPriority($priority)
        {
            return isset($this->values[$priority]);
        }
    }
