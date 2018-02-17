<?php

use Octo\App;
use Octo\Inflector;

class testHook
{
    /**
     * @param int $a
     * @param int $b
     *
     * @return int
     */
    public function test(int $a, int $b): int
    {
        return $a + $b;
    }

    public function injection(string $string, Inflector $inflector): string
    {
        return $inflector::upper($string);
    }
}

class HookTest extends TestCase
{
    /**
     * @var \Octo\Fast;
     */
    protected $app;

    public function setUp()
    {
        $this->app = App::create();

        $this->app::hook('Inflector@dummy', function ($a, $b) {
            return $a * $b;
        });
    }

    public function testClosure()
    {
        $res = $this->app::callHook('Inflector@dummy', 5, 3);

        $this->assertSame(15, $res);
    }

    public function testInjection()
    {
        $res = $this->app::callHook(testHook::class . '@injection', 'test');

        $this->assertSame('TEST', $res);

        $this->app::hook(testHook::class . '@injection', function (string $string, Inflector $inflector): string {
            return $inflector::lower($string);
        });

        $res = $this->app::callHook(testHook::class . '@injection', 'TEST');

        $this->assertSame('test', $res);
    }

    public function testClass()
    {
        $res = $this->app::callHook(testHook::class . '@test', 5, 3);

        $this->assertSame(8, $res);

        $this->app::hook(testHook::class . '@test', function (int $a, int $b): int {
            return $a * $b;
        });

        $res = $this->app::callHook(testHook::class . '@test', 5, 3);

        $this->assertSame(15, $res);
    }
}
