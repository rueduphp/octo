<?php
namespace Octo;

class Node
{
    protected $definition;
    protected $children = [];

    public function __construct($definition)
    {
        $this->definition = $definition;
    }

    public function getNode()
    {
        return $this->definition;
    }

    /**
     * @param $definition
     *
     * @return $this
     */
    public function addChild($definition)
    {
        $child = (new Tree)->setRoot(new self($definition));

        $this->children[] = $child;

        return $this;
    }
}