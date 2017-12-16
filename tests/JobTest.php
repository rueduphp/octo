<?php

use Octo\FastJobInterface;
use Octo\FastNow;
use Octo\Work;

class MyJob implements FastJobInterface
{
    private $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function process()
    {

    }

    public function onSuccess()
    {
        Octo\set('jobtest', $this->user);
    }

    public function onFail()
    {

    }
}

class JobTest extends TestCase
{
    public function setUp()
    {
        $this->getContainer()->register(Work::class, function () {
            return new Work(new FastNow('jobs'));
        });

        $this->helperSet('jobtest', 'foo');
    }

    public function testNow()
    {
        $user = ['name' => 'foo', 'firstname' => 'bar'];

        $this->job(MyJob::class, [$user])->now();
        $processed = $this->job()->process();

        $this->assertEquals(1, $processed);
        $this->assertSame($user, $this->helperGet('jobtest'));
        $this->assertEmpty($this->job()->schedule());
    }

    public function testLater()
    {
        $user = ['name' => 'foo', 'firstname' => 'bar'];

        $this->job(MyJob::class, [$user])->in(15);
        $this->job(MyJob::class, [$user])->at(strtotime('+15 minute'));
        $this->job(MyJob::class, [$user])->at(strtotime('+2 second'));

        $processed = $this->job()->process();
        $schedule = $this->job()->schedule();

        $this->assertEquals(0, $processed);
        $this->assertCount(3, $schedule);
        $this->assertEquals($schedule[1], $schedule[2]);
        $this->assertEquals([MyJob::class => date('d/m/Y H:i:s', strtotime('+2 second'))], $schedule[0]);
    }
}