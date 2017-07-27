<?php
    use Octo\Route;
    use Octo\ControllerBase;
    use function Octo\context;

    class DummyController extends ControllerBase
    {
        public function getIndex()
        {
            context('app')['test'] += 1;
        }

        public function getTest($add)
        {
            context('app')['test'] += $add;
        }
    }

    class RoutesTest extends TestCase
    {
        /** @test */
        public function withArray()
        {
            Route::get('/', [DummyController::class, 'index', false]);
            Route::get('add/(.*)', [DummyController::class, 'test', false]);

            $_SERVER['SERVER_PROTOCOL'] = 'HTTP';
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/';

            $this->foundry('router')->setBaseRoute('')->run();
            $this->assertEquals(1, context('app')['test']);

            $_SERVER['REQUEST_URI'] = '/add/15';

            $this->foundry('router')->setBaseRoute('')->run();
            $this->assertEquals(16, context('app')['test']);
        }
    }
