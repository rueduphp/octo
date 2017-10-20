<?php

class TreeTest extends TestCase
{
    public function testNode()
    {
        $tree = $this->tree($this->node(['name' => 'test']));

        /**
         * @var Octo\Node $node
         */
        $node = $tree->node();

        $this->assertNull($node->getParent());

        $child = $this->node(['name' => 'test2'], false);

        $child = $node->addChild($child);

        $this->assertEquals('test', $child->getParent()->reveal()->getName());
    }
}