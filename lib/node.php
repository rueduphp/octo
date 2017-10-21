<?php
namespace Octo;

use function array_key_exists;
use const JSON_PRETTY_PRINT;

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
     * @var Node[]
     */
    protected $children = [];

    /**
     * @param NodeData|null $definition
     * @param array $children
     */
    public function __construct(NodeData $definition = null, array $children = [])
    {
        $this->definition = is_null($definition) ? new NodeData() : $definition;

        if (!empty($children)) {
            $this->setChildren($children);
        }
    }

    /**
     * @return NodeData
     */
    public function is()
    {
        return $this->definition;
    }

    /**
     * @return NodeData
     */
    public function reveal()
    {
        return $this->definition;
    }

    /**
     * @return NodeData
     */
    public function data()
    {
        return $this->reveal();
    }

    /**
     * @return NodeData
     */
    public function getData()
    {
        return $this->reveal();
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
     * @return Node[]
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param NodeData|Node $concern
     *
     * @return Node
     */
    public function addChild($concern)
    {
        if ($concern instanceof NodeData) {
            $node = new self($concern);
        } else {
            $node = $concern;
        }

        $node->setParent($this);

        $this->children[] = $node;

        return $node;
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
            if ($child == $myChild) {
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
                function (Node $item) use ($current) {
                    return $item != $current;
                }
            )
        );
    }

    /**
     * @return Node[]
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
        return empty($this->children);
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
     * @param int $nth
     *
     * @return Node
     */
    public function nth(int $nth)
    {
        $node = $this;

        $i = 0;

        while ($parent = $node->getParent()) {
            if ($i < $nth) {
                $node = $parent;

                $i++;
            } else {
                break;
            }
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
            $size += $child->getSize();
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
            $heights[] = $child->getHeight();
        }

        return max($heights) + 1;
    }

    /**
     * @return Node
     */
    public function getFamily()
    {
        return $this->isChild() ? $this->root() :$this;
    }

    /**
     * @param array $data
     * @return array
     */
    public function toArray($data =  [])
    {
        $row = [];

        if ($this->isRoot() && !$this->isLeaf()) {
            $row = [
                'type' => 'root',
                'data' => $this->getData()->toArray(),
                'children' => []
            ];
        } elseif ($this->isLeaf()) {
            $row = [
                'type' => 'leaf',
                'data' => $this->getData()->toArray()
            ];
        } elseif (!$this->isRoot() && !$this->isLeaf()) {
            $row = [
                'type' => 'child',
                'data' => $this->getData()->toArray(),
                'children' => []
            ];
        }

        if (array_key_exists('children', $row)) {
            foreach ($this->getChildren() as $child) {
                $row['children'][] = $child->toArray();
            }
        }

        $data[] = $row;

        return $data;
    }

    /**
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    /**
     * @return void
     */
    protected function removeParentFromChildren()
    {
        foreach ($this->getChildren() as $child) {
            $child->setParent(null);
        }
    }

    /**
     * @param string $method
     * @param array $params
     *
     * @return mixed
     */
    public function __call(string $method, array $params)
    {
        return call_user_func_array([$this->definition, $method], $params);
    }
}