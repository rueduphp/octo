<?php

class TreeTest extends TestCase
{
    public function testNode()
    {
        /**
         * @var Octo\Node $node
         */
        $node = $this->node(['name' => 'test']);

        $this->assertNull($node->getParent());

        $child = $this->node(['name' => 'test2']);
        $child = $node->addChild($child);

        $child2 = $this->node(['name' => 'test3']);
        $child2 = $node->addChild($child2);

        $child3 = $this->node(['name' => 'test4']);
        $child3 = $child2->addChild($child3);

        $this->assertEquals('test', $child->getParent()->getName());
        $this->assertEquals('test', $child2->getParent()->getName());

        $this->assertCount(1, $child->getAncestors());
        $this->assertCount(1, $child2->getAncestors());

        $this->assertCount(1, $child->getNeighbors());
        $this->assertCount(1, $child2->getNeighbors());

        $this->assertEquals(1, $child->getDepth());
        $this->assertEquals(1, $child2->getDepth());
        $this->assertEquals(2, $child3->getDepth());
        $this->assertEquals(0, $node->getDepth());

        $this->assertEquals(0, $child->getHeight());
        $this->assertEquals(1, $child2->getHeight());
        $this->assertEquals(0, $child3->getHeight());

        $this->assertEquals(1, $child->getSize());
        $this->assertEquals(2, $child2->getSize());
        $this->assertEquals(1, $child3->getSize());
        $this->assertEquals(4, $node->getSize());

        $this->assertSame($child->getParent(), $child2->getParent());
        $this->assertSame($child3->getParent(), $child2);
        $this->assertSame($child3->root(), $child2->root());
        $this->assertSame($child3->root(), $child->root());
        $this->assertSame($child2->root(), $child->root());
        $this->assertSame($node, $node->root());
        $this->assertSame($node, $child->root());
        $this->assertSame($node, $child2->root());

        $this->assertSame($node, $child3->nth(2));
        $this->assertSame($child2, $child3->nth(1));
        $this->assertSame($node, $child2->nth(1));

        $this->assertEquals('test', $node->getName());
        $this->assertEquals('test2', $child->getName());
        $this->assertEquals('test3', $child2->getName());
        $this->assertEquals('test4', $child3->getName());
        $this->assertNull($this->node()->getName());

        $this->assertTrue($node->isRoot());
        $this->assertTrue($child->isChild());
        $this->assertTrue($child2->isChild());
        $this->assertTrue($child->isLeaf());
        $this->assertTrue($child3->isLeaf());
        $this->assertFalse($child2->isLeaf());
        $this->assertFalse($child->isRoot());
    }
}