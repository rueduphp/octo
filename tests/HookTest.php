<?php

use Octo\App;
use Octo\Custom;
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

    /**
     * @param string $string
     * @param Inflector $inflector
     *
     * @return string
     */
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

    public function setUp(): void
    {
        $this->app = App::create();

        $this->app::hook('Inflector@dummy', function ($a, $b) {
            return $a * $b;
        });
    }

    public function testCustom()
    {
        $custom = new Custom(testHook::class);

        $this->assertSame('TEST', $custom->injection('test'));

        $custom->override('injection', function (string $string, Inflector $inflector): string {
            return $inflector::lower($string);
        });

        $this->assertSame('test', $custom->injection('TEST'));

        $custom->override('injection', function (string $string, Inflector $inflector): string {
            return $inflector::upper($string);
        });
    }

    /**
     * @throws ReflectionException
     */
    public function testClosure(): void
    {
        $res = $this->app::callHook('Inflector@dummy', 5, 3);

        $this->assertSame(15, $res);
    }

    /**
     * @throws ReflectionException
     */
    public function testInjection(): void
    {
        $res = $this->app::callHook(testHook::class . '@injection', 'test');

        $this->assertSame('TEST', $res);

        $this->app::hook(testHook::class . '@injection', function (string $string, Inflector $inflector): string {
            return $inflector::lower($string);
        });

        $res = $this->app::callHook(testHook::class . '@injection', 'TEST');

        $this->assertSame('test', $res);
    }

    /**
     * @throws ReflectionException
     */
    public function testClass(): void
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
