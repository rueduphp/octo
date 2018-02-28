<?php

use Octo\FastEvent;
use Octo\Inflector;
use function Octo\dispatch;
use function Octo\get;
use function Octo\incr;
use function Octo\set;

class DispatchJob
{
    /**
     * @param int $by
     * @param Inflector $i
     * @return Inflector
     */
    public function fire(int $by, Inflector $i): Inflector
    {
        incr('dispatcher', $by);

        return $i;
    }
}

class EventsTest extends TestCase
{
    /**
     * @var FastEvent
     */
    private $manager;

    /**
     * @throws ReflectionException
     */
    public function setUp()
    {
        $this->manager = $this->getEventManager();

        $event = $this->manager->on('test', function ($num) {
            incr('eventval', $num);
        });

        $event->halt(true);

        $this->manager->on('test2', function ($num) {
            incr('eventval', $num);
        });
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function testDispatch()
    {
        $result = dispatch(DispatchJob::class, 5);
        $this->assertInstanceOf(Inflector::class, $result);
        $this->assertSame(5, get('dispatcher'));
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function testFire()
    {
        set('eventval', 5);

        $this->assertTrue($this->manager->has('test'));

        $this->manager->fire('test', 10);
        $this->assertEquals(15, get('eventval'));

        $this->manager->fire('test', 10);
        $this->assertEquals(15, get('eventval'));

        $this->manager->fire('test2', 100);
        $this->assertEquals(15, get('eventval'));

        $this->manager->fire('test2', 100);
        $this->assertEquals(15, get('eventval'));

        $this->manager->delete('test');
        $this->assertFalse($this->manager->has('test'));
    }
}