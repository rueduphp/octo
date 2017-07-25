<?php
    use Octo\Redys;
    use function Octo\em as dbo;

    class Dummy {}

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
        public function coords()
        {
            $infos = $this->lib('geo')->addressByLatLng(48.8163897,-3.0640017);

            $this->assertEquals('19 Route de Loguivy de la Mer, 22620 Ploubazlanec, France', $infos['formatted_address']);

            $infos = $this->lib('geo')->getCoordsMap('Tour eiffel');

            $this->assertEquals('Champ de Mars, 5 Avenue Anatole France, 75007 Paris, France', $infos['normalized_address']);
            $this->assertEquals(48.8583701, $infos['lat']);

            $infos = $this->lib('geo')->getCoordsMap('Musée Grévin');

            $this->assertEquals('10 Boulevard Montmartre, 75009 Paris, France', $infos['normalized_address']);
            $this->assertEquals(48.8718378, $infos['lat']);
        }

        /** @test */
        public function redis()
        {
            Redys::set('test', 1);

            $dt = $this->lib('time');
            $dt2 = $this->fromTs(Redys::age('test'));

            $this->assertEquals($dt, $dt2);
            $this->assertEquals(1, Redys::get('test'));
            $this->assertEquals('default', Redys::get('test2', 'default'));
            $this->assertCount(1, Redys::all());

            Redys::forget('test');

            $this->assertCount(0, Redys::all());
        }

        /**
         * @test
         */
        public function jobs()
        {
            $job = $this->job();

            $job->in(Tests\Job::class, 1);

            $this->assertEquals(1, dbo('systemClosure')->count());
            $this->assertEquals(Octo\Cacheredis::class, get_class(dbo('systemClosure')->driver));
        }

        /**
         * @test
         */
        public function wiring()
        {
            $this->wire(PDO::class, function () {
                return new Dummy;
            });

            $this->assertEquals(Dummy::class, get_class($this->maker(PDO::class)));
        }
    }
