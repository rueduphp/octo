<?php
use Octo\Smart;

class PipeTest
{
    public function __invoke($request, $next)
    {
        Octo\incr(__CLASS__);

        return $next($request);
    }
}

class SmartTest extends TestCase
{
    private $test = 0;
    /**
     * @throws ReflectionException
     */
    public function testApp()
    {
        $app = new Smart;

        $app
            ->step(function ($request, $next) {
                $this->test++;

                return $next($request);
            })
            ->step(PipeTest::class)
            ->step(function () {
                $this->test++;

                return true;
            })
        ;

        $response = $app->run();

        $this->assertTrue($response);
        $this->assertSame(2, $this->test);
        $this->assertSame(1, Octo\get(PipeTest::class));
    }
}