<?php
namespace Octo;

use function FastRoute\TestFixtures\empty_options_cached;

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
     * @var Tree
     */
    protected $tree;

    /**
     * @var Tree[]
     */
    protected $children = [];

    /**
     * @param NodeData $definition
     * @param NodeData[] $children
     */
    public function __construct(NodeData $definition, array $children = [])
    {
        $this->definition = $definition;

        if (!empty($children)) {
            $this->setChildren($children);
        }
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

    /**
     * @param NodeData[] $children
     *
     * @return $this
     */
    public function setChildren(array $children)
    {
        foreach ($children as $child) {
            $this->addChild($child);
        }

        return $this;
    }

    /**
     * @param Node $child
     *
     * @return $this
     */
    public function removeChild(Node $child)
    {
        foreach ($this->children as $key => $myChild) {
            if ($child == $myChild->node()) {
                unset($this->children[$key]);
            }
        }

        $this->children = array_values($this->children);

        $child->setParent(null);

        return $this;
    }

    /**
     * @param Tree $tree
     *
     * @return $this
     */
    public function setTree(Tree $tree)
    {
        $this->tree = $tree;

        return $this;
    }

    /**
     * @return $this
     */
    public function removeAllChildren()
    {
        $this->removeParentFromChildren();

        $this->children = [];

        return $this;
    }

    /**
     * @return Tree
     */
    public function getTree()
    {
        return $this->tree;
    }

    /**
     * @return array
     */
    public function getAncestors()
    {
        $parents = [];
        $node = $this;

        while ($parent = $node->getParent()) {
            array_unshift($parents, $parent);
            $node = $parent;
        }

        return $parents;
    }

    /**
     * @return array
     */
    public function getAncestorsAndSelf()
    {
        return array_merge($this->getAncestors(), [$this]);
    }

    /**
     * @return array
     */
    public function getNeighbors()
    {
        $neighbors = $this->getParent()->getChildren();

        $current = $this;

        return array_values(
            array_filter(
                $neighbors,
                function ($item) use ($current) {
                    return $item != $current;
                }
            )
        );
    }

    /**
     * @return Tree[]
     */
    public function getNeighborsAndSelf()
    {
        return $this->getParent()->getChildren();
    }

    /**
     * @return bool
     */
    public function isLeaf()
    {
        return count($this->children) === 0;
    }

    /**
     * @return bool
     */
    public function isRoot()
    {
        return $this->getParent() === null;
    }

    /**
     * @return bool
     */
    public function isChild()
    {
        return $this->getParent() !== null;
    }

    /**
     * Find the root of the node
     *
     * @return Node
     */
    public function root()
    {
        $node = $this;

        while ($parent = $node->getParent()) {
            $node = $parent;
        }

        return $node;
    }

    /**
     * Return the number of nodes in a tree
     *
     * @return int
     */
    public function getSize()
    {
        $size = 1;
        foreach ($this->getChildren() as $child) {
            $size += $child->node()->getSize();
        }

        return $size;
    }

    /**
     * Return the distance from the current node to the root.
     *
     * Warning, can be expensive, since each descendant is visited
     *
     * @return int
     */
    public function getDepth()
    {
        if ($this->isRoot()) {
            return 0;
        }

        return $this->getParent()->getDepth() + 1;
    }

    /**
     * Return the height of the tree whose root is this node
     *
     * @return int
     */
    public function getHeight()
    {
        if ($this->isLeaf()) {
            return 0;
        }

        $heights = [];

        foreach ($this->getChildren() as $child) {
            $heights[] = $child->node()->getHeight();
        }

        return max($heights) + 1;
    }

    /**
     * @return void
     */
    private function removeParentFromChildren()
    {
        foreach ($this->getChildren() as $child) {
            $child->node()->setParent(null);
        }
    }
}