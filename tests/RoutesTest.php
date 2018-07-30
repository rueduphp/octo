<?php
use Octo\Route;
use Octo\Router;
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
        $_SERVER['REQUEST_METHOD']  = 'GET';
        $_SERVER['REQUEST_URI']     = '/';

        $this->gi()->make(Router::class)->setBaseRoute('')->run();
        $this->assertEquals(1, $this->context('app')['test']);

        $_SERVER['REQUEST_URI'] = '/add/15';

        $this->gi()->make(Router::class)->setBaseRoute('')->run();
        $this->assertEquals(16, $this->context('app')['test']);
    }

    public function testRedirector()
    {
        $this->getRouter()
            ->addRoute(
                new Zend\Expressive\Router\Route(
                    '/',
                    function () {
                        return 'home';
                    },
                    ['GET'],
                    'home'
                )
            )
        ;

        /** @var \GuzzleHttp\Psr7\MessageTrait $response */
        $response = $this->redirect()->home();

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/', current($response->getHeader('Location')));
    }
}
