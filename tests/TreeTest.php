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
    }
}