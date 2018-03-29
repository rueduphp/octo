<?php
    use Octo\Inflector;
    use Octo\Redys;

    class Foo {}
    class Dummy
    {
        public function test()
        {
            return 200;
        }
    }

    class UtilsTest extends TestCase
    {
        public function setUp()
        {
            parent::setUp();
            Redys::flush();
        }

        /** @test */
        public function firstReturnsFirstItemInCollection()
        {
            $c = $this->coll(['foo', 'bar']);
            $this->assertEquals('foo', $c->first());
        }

        /** @test */
        public function lastReturnsLastItemInCollection()
        {
            $c = $this->coll(['foo', 'bar']);
            $this->assertEquals('bar', $c->last());
        }

        /** @test */
        public function firstWithCallback()
        {
            $data = $this->coll(['foo', 'bar', 'baz']);

            $result = $data->first(function ($key, $value) {
                return $value === 'bar';
            });

            $this->assertEquals('bar', $result);
        }

        /** @test */
        public function firstWithCallbackAndDefault()
        {
            $data = $this->coll(['foo', 'bar']);

            $result = $data->first(function ($key, $value) {
                return $value === 'baz';
            }, 'default');

            $this->assertEquals('default', $result);
        }

        /** @test */
        public function shiftReturnsAndRemovesFirstItemInCollection()
        {
            $c = $this->coll(['foo', 'bar']);

            $this->assertEquals('foo', $c->shift());
            $this->assertEquals('bar', $c->first());
        }

        /** @test */
        public function popReturnsAndRemovesLastItemInCollection()
        {
            $c = $this->coll(['foo', 'bar']);

            $this->assertEquals('bar', $c->pop());
            $this->assertEquals('foo', $c->first());
        }

        /** @test */
        public function sortcoll()
        {
            $c = $this->coll([['name' => 'foo'], ['name' => 'bar']])->sortBy('name');

            $this->assertEquals('bar', $c->first()['name']);

            $this->assertEquals('foo', $c->sortByDesc('name')->first()['name']);
        }

        /** @test */
        public function mapcoll()
        {
            $c = $this->coll([['name' => 'foo'], ['name' => 'bar']])->map(function ($row, $index) {
                if ($row['name'] == 'bar') {
                    $this->context('app')->test = $index;
                    $row['name'] = 'baz';
                }

                return $row;
            });

            $this->assertEquals('baz', $c->pop()['name']);
            $this->assertEquals(1, $this->context('app')->test);
            $this->assertEquals(1, $c->count());
            $this->assertEquals('foo', $c->shift()['name']);
            $this->assertEquals(0, $c->count());
        }

        /** @test */
        public function it_should_be_uppered()
        {
            $this->assertEquals('BAR', $this->lib('inflector')->upper('bar'));
        }

        /** @test */
        public function it_should_be_lowered()
        {
            $this->assertEquals('bar', $this->lib('inflector')->lower('BAR'));
        }

        /** @test */
        // public function coords()
        // {
        //     $infos = $this->lib('geo')->addressByLatLng(48.8163897,-3.0640017);

        //     $this->assertEquals('19 Route de Loguivy de la Mer, 22620 Ploubazlanec, France', $infos['formatted_address']);

        //     $infos = $this->lib('geo')->getCoordsMap('Tour eiffel');

        //     $this->assertEquals('Champ de Mars, 5 Avenue Anatole France, 75007 Paris, France', $infos['normalized_address']);
        //     $this->assertEquals(48.8583701, $infos['lat']);

        //     $infos = $this->lib('geo')->getCoordsMap('Musée Grévin');

        //     $this->assertEquals('10 Boulevard Montmartre, 75009 Paris, France', $infos['normalized_address']);
        //     $this->assertEquals(48.8718378, $infos['lat']);
        // }

        /** @test */
        public function redis()
        {
            Redys::set('test', 1);

            /** @var \Octo\Time $dt */
            $dt     = $this->lib('time');
            $dt2    = $this->fromTs(Redys::age('test'));


            $this->assertSame(0, $dt->diff($dt2)->s);
            $this->assertSame(1, (int) Redys::get('test'));
            $this->assertSame('default', Redys::get('test2', 'default'));
            $this->assertCount(1, Redys::all());

            Redys::forget('test');

            $this->assertCount(0, Redys::all());
        }

        /**
         * @test
         */
        public function wiring()
        {
            $this->wire(Foo::class, function () {
                return new Dummy;
            });

            $this->assertEquals(Dummy::class, get_class($this->maker(Foo::class)));
            $this->assertEquals(Dummy::class, get_class($pdo = $this->container(Foo::class)));

            $this->assertEquals(200, $pdo->test());
        }

        /**
         * @test
         */
        public function superdiTest()
        {
            /* Registry */
            sdi()->registry('test', 'dummy');
            $this->assertEquals('dummy', sdi()->registry('test'));

            $foo = sdi()->mock(Foo::class);

            $foo->test(function () {
                return 20;
            });

            $this->assertEquals(200, $foo->test());

            sdi()->register(Foo::class, function () {
                return new Dummy;
            });

            $foo = sdi()->resolve(Foo::class);

            $this->assertEquals(Dummy::class, get_class($foo));

            $this->assertEquals(200, $foo->test());

            $app = sdi();

            $app['test'] = 'hello';

            $app->dummy(function () {
                return 20;
            });

            $this->assertEquals('hello', sdi()->test);
            $this->assertEquals('hello', sdi()->getTest());
            $this->assertEquals('test2', sdi()->setTest('test2')->test);
            $this->assertEquals(20, sdi()->dummy());

            sdi()->register(Inflector::class, function () {
                return Octo\dyn(new Inflector);
            });

            $this->assertEquals(
                Octo\Dyn::class,
                get_class(
                    sdi()->factory(Inflector::class)
                )
            );

            $this->assertEquals(
                Inflector::class,
                get_class(
                    sdi()->factory(Inflector::class)->getNative()
                )
            );
        }
    }
