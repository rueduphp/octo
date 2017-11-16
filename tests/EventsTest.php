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
            Octo\set('evval', Octo\get('evval') + $num);
        });

        $event->halt(true);

        $this->manager->on('test2', function ($num) {
            Octo\set('evval', Octo\get('evval') + $num);
        });
    }

    public function testFire()
    {
        Octo\set('evval', 5);

        $this->assertTrue($this->manager->has('test'));

        $this->manager->fire('test', 10);
        $this->manager->fire('test', 10);
        $this->manager->fire('test2', 100);
        $this->manager->fire('test2', 100);

        $this->assertEquals(15, Octo\get('evval'));

        $this->manager->delete('test');
        $this->assertFalse($this->manager->has('test'));
    }
}