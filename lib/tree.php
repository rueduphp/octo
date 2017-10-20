<?php
    namespace Octo;

    class Tree
    {
        /**
         * @var bool
         */
        protected $isRoot = true;

        /**
         * @var Node
         */
        protected $node;

        /**
         * @param Node $node
         */
        public function __construct(Node $node)
        {
            $this->node = node;

            if ($this->node->hasParent()) {
                $this->isRoot = false;
            }

            $node->setTree($this);
        }

        /**
         * @return bool
         */
        public function isRoot()
        {
            return $this->isRoot;
        }

        /**
         * @return Node
         */
        public function get()
        {
            return $this->node;
        }

        /**
         * @return Node
         */
        public function node()
        {
            return $this->node;
        }

        /**
         * @return Tree[]
         */
        public function children()
        {
            return $this->node->getChildren();
        }

        /**
         * @return Tree[]
         */
        public function nodes()
        {
            return $this->node->getChildren();
        }

        /**
         * @param string $method
         * @param array $params
         *
         * @return mixed
         */
        public function __call(string $method, array $params)
        {
            return call_user_func_array([$this->node, $method], $params);
        }
    }