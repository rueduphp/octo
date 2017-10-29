<?php

use Octo\Bank;
use Octo\FastNow;
use Octo\FastRedis;
use Octo\Object;
use Octo\Time;

class BankTest extends TestCase
{
    /**
     * @var Bank $db;
     */
    private $db;

    public function setUp()
    {
        parent::setUp();

        $this->db = new Bank('test', 'test', new FastNow('bank'));
        $udb = new Bank('test', 'user', new FastNow('bank'));

        $faker = $this->faker();

        for ($i = 0; $i < 10; ++$i) {
            $udb->store([
                'name' => $faker->name
            ]);
        }

        for ($i = 0; $i < 1000; ++$i) {
            if ($i < 1) {
                $name = 'test';
            } else {
                $name = 'test' . ($i + 1);
            }

            $this->db->store([
                'user_id' => rand(1, 10),
                'price' => ($i + 1) * 100,
                'name' => $name,
                'slug' => $faker->slug
            ]);
        }
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

        $this->assertCount(1000,        $results);
        $this->assertEquals('test',     $results[1]['name']);
        $this->assertEquals('test2',    $results[2]['name']);
        $this->assertEquals('test3',    $results[3]['name']);
        $this->assertEquals('test103',  $results[103]['name']);
    }

    public function testHydrator()
    {
        $rows = $this->db->hydrate();

        foreach ($rows as $row) {
            $this->assertInstanceOf(Object::class, $row);

            if ($row->getId() === 1) {
                $this->assertSame('test',   $row->getName());
            } elseif ($row->getId() === 2) {
                $this->assertSame('test2',  $row->getName());
            }

            $this->assertSame($row, $row->save());
        }

        $row = $this->db->findHydrate(1);

        $this->assertInstanceOf(Time::class, $row->getCreatedAt());
    }

    public function testWhere()
    {
        $count = $this->db->where('price', '<', 100)->count();
        $this->assertEquals(0, $count);

        $count = $this->db->where('price', '>', 100)->count();
        $this->assertEquals(999, $count);

        $count = $this->db->where('price', '>', 200)->count();
        $this->assertEquals(998, $count);

        $this->assertEquals(100000, $this->db->max('price'));
        $this->assertEquals(100, $this->db->min('price'));
        $this->assertEquals(50050, $this->db->avg('price'));
    }

    public function testFindBy()
    {
        /** @var Object $row */
        $row = $this->db->findByName('test105')->firstHydrate();

        $this->assertEquals(105, $row->getId());
        $this->assertEquals(105, $row['id']);

        $row->delete();
        $this->assertNull($this->db->findHydrate(105));
    }

    public function testIn()
    {
        $count = $this->db->in(
            'id',
            [105, 555, 888]
        )->count();

        $this->assertEquals(3, $count);
    }

    public function testLike()
    {
        $count = $this->db->like('slug', '*e*')->count();

        $this->assertGreaterThan(0, $count);

        $count = $this->db->likeSlug('*e*')->count();

        $this->assertGreaterThan(0, $count);
    }
}