<?php

use Octo\Bank;
use Octo\FastNow;
use Octo\FastRedis;
use Octo\Objet;
use Octo\Time;

class BankTest extends TestCase
{
    /**
     * @var Bank $db;
     */
    private $db;

    /**
     * @var Bank $udb;
     */
    private $udb;

    /**
     * @var Bank $udb;
     */
    private $postDb;

    public function setUp()
    {
        parent::setUp();

        $this->gi(Octo\FastStorageInterface::class, function () {
            return new FastNow('bank');
        });

        $this->db           = new Bank('test', 'test', new FastNow('bank'));
        $this->udb = $udb   = new Bank('test', 'user', new FastNow('bank'));
        $this->postDb       = new Bank('test', 'post', new FastNow('bank'));

        $faker = $this->faker();

        for ($i = 0; $i < 10; ++$i) {
            $udb->store([
                'age'   => ($i + 1) * 5,
                'name'  => $faker->name
            ]);
        }

        for ($i = 0; $i < 1000; ++$i) {
            if ($i < 1) {
                $name = 'test';
            } else {
                $name = 'test' . ($i + 1);
            }

            $this->db->store([
                'user_id'   => rand(1, 10),
                'price'     => ($i + 1) * 100,
                'name'      => $name,
                'slug'      => $faker->slug
            ]);
        }
    }

    public function tearDown()
    {
        parent::tearDown();

        $this->udb->drop();
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
            $this->assertSame($row, $row->save());
            $this->assertInstanceOf(Objet::class, $row);

            if ($row->getId() === 1) {
                $this->assertSame('test',   $row->getName());
            } else {
                $this->assertSame('test'. $row->getId(),  $row->getName());
            }
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
        $this->assertEquals(1, $this->db->where('price', 100)->count());
        $this->assertEquals(1, $this->db->where(function ($item) {
            return $item['price'] === 100;
        })->count());
        $this->assertEquals(1, $this->db->where('price', 200)->count());
        $this->assertEquals(1, $this->db->where('price', 100000)->count());

        $count = $this->db->where('price', '>', 200)->count();
        $this->assertEquals(998, $count);

        $this->assertEquals(100000, $this->db->max('price'));
        $this->assertEquals(100,    $this->db->min('price'));
        $this->assertEquals(50050,  $this->db->avg('price'));
    }

    public function testFindBy()
    {
        /** @var Objet $row */
        $row = $this->db->findByName('test105')->firstHydrate();

        $this->assertEquals(105, $row->getId());
        $this->assertEquals(105, $row['id']);

        $row->delete();
        $this->assertNull($this->db->findHydrate(105));
        $this->assertNull($this->db->find(105));
    }

    public function testIn()
    {
        $count = $this->db->in(
            'id',
            [105, 555, 888]
        )->count();

        $this->assertEquals(3, $count);
    }

    public function testFirstLast()
    {
        $this->assertSame(1, $this->db->firstCache()['id']);
        $this->assertSame(1, $this->udb->firstCache()['id']);

        $this->assertTrue(is_array($this->db->firstCacheHydrate()->user));
        $this->assertFalse(is_array($this->db->firstCacheHydrate()->user()));

        $this->assertGreaterThan(0, $this->udb->firstCacheHydrate()->tests()->count());

        $this->assertSame(1000, $this->db->lastCache()['id']);
        $this->assertSame(10, $this->udb->lastCache()['id']);

        $this->assertSame(6, $this->udb->between('age', 5, 30)->count());
    }

    public function testLike()
    {
        $count = $this->db->like('slug', '*e*')->count();

        $this->assertGreaterThan(0, $count);

        $count2 = $this->db->likeSlug('*e*')->count();

        $this->assertGreaterThan(0, $count2);
        $this->assertSame($count, $count2);
    }
}
