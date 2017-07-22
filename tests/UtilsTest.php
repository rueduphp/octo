<?php
    use Octo\Redys;

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
        }

        /** @test */
        public function redis()
        {
            Redys::set('test', 'OK');

            $this->assertEquals('OK', Redys::get('test'));
            $this->assertEquals('default', Redys::get('test2', 'default'));

            $this->assertCount(1, Redys::keys('*'));
        }
    }
