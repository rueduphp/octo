<?php

use Octo\Bank;
use Octo\FastRedis;
use Octo\Object;

class BankTest extends TestCase
{
    /**
     * @var Bank $db;
     */
    private $db;

    public function setUp()
    {
        parent::setUp();

        $this->db = new Bank('test', 'test', new FastRedis('bank'));

        $this->db->store([
            'price' => 120,
            'name' => 'test'
        ]);

        $this->db->store([
            'price' => 500,
            'name' => 'test2'
        ]);
    }

    public function tearDown()
    {
        parent::tearDown();

        $this->db->drop();
    }

    public function testAdd()
    {
        $row = $this->db->find(1);

        $this->assertEquals('test', $row['name']);

        $this->db->delete(1);

        $this->assertNull($this->db->find(1));

        $row = $this->db->find(2);

        $this->assertEquals('test2', $row['name']);

        $this->db->delete(2);

        $this->assertNull($this->db->find(2));

        $this->db->drop();

        $this->assertCount(0, $this->db->all());
    }

    public function testSelect()
    {
        $results = $this->db->select('name');

        $this->assertCount(2, $results);
        $this->assertEquals('test', $results[1]['name']);
        $this->assertEquals('test2', $results[2]['name']);
    }

    public function testHydrator()
    {
        $rows = $this->db->hydrate();

        foreach ($rows as $row) {
            $this->assertInstanceOf(Object::class, $row);

            if ($row->getId() === 1) {
                $this->assertSame('test', $row->getName());
            } elseif ($row->getId() === 2) {
                $this->assertSame('test2', $row->getName());
            }

            $this->assertSame($row, $row->save());
        }
    }

    public function testWhere()
    {
        $count = $this->db->where('price', '>', 100)->count();
        $this->assertEquals(2, $count);

        $count = $this->db->where('price', '>', 200)->count();
        $this->assertEquals(1, $count);
    }
}