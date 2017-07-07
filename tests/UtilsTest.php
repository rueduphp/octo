<?php
    class UtilsTest extends TestCase
    {
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
    }
