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

        public function getChildren()
        {
            return $this->node->getchildren();
        }
    }