<?php
    namespace Octo;

    class Tree
    {
        protected $root;

        public function setRoot(Node $node)
        {
            $this->root = $node;

            return $this;
        }

        public function getRoot()
        {
            return $this->root;
        }
    }