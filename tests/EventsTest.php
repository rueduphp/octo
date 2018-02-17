<?php

use Octo\FastEvent;

class EventsTest extends TestCase
{
    /**
     * @var FastEvent
     */
    private $manager;

    public function setUp()
    {
        $this->manager = $this->getEventManager();

        $event = $this->manager->on('test', function ($num) {
            Octo\set('eventval', Octo\get('eventval') + $num);
        });

        $event->halt(true);

        $this->manager->on('test2', function ($num) {
            Octo\set('eventval', Octo\get('eventval') + $num);
        });
    }

    /**
     * @throws Exception
     */
    public function testFire()
    {
        Octo\set('eventval', 5);

        $this->assertTrue($this->manager->has('test'));

        $this->manager->fire('test', 10);
        $this->assertEquals(15, Octo\get('eventval'));

        $this->manager->fire('test', 10);
        $this->assertEquals(15, Octo\get('eventval'));

        $this->manager->fire('test2', 100);
        $this->assertEquals(15, Octo\get('eventval'));

        $this->manager->fire('test2', 100);
        $this->assertEquals(15, Octo\get('eventval'));

        $this->manager->delete('test');
        $this->assertFalse($this->manager->has('test'));
    }
}