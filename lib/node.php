<?php
namespace Octo;

class NodeData extends Object {}

class Node
{
    /**
     * @var NodeData
     */
    protected $definition;

    /**
     * @var Node
     */
    protected $parent;

    /**
     * @var Tree[]
     */
    protected $children = [];

    /**
     * @param NodeData $definition
     */
    public function __construct(NodeData $definition)
    {
        $this->definition = $definition;
    }


    /**
     * @return NodeData
     */
    public function get()
    {
        return $this->definition;
    }

    /**
     * @param Node $node
     * @return $this
     */
    public function setParent(Node $node)
    {
        $this->parent = $node;

        return $this;
    }

    /**
     * @return null|Node
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @return bool
     */
    public function hasParent()
    {
        return !is_null($this->parent);
    }

    /**
     * @return bool
     */
    public function hasChildren()
    {
        return !empty($this->children);
    }

    /**
     * @return Tree[]
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param NodeData $definition
     *
     * @return $this
     */
    public function addChild(NodeData $definition)
    {
        $node = new self($definition);
        $node->setParent($this);
        $child = new Tree($node);

        $this->children[] = $child;

        return $this;
    }
}