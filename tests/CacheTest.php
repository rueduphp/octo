<?php
    class CacheTest extends TestCase
    {
        /**
         * @throws Exception
         */
        public function testfmr()
        {
            $this->fmr()->set('test', 'dummy');

            $this->assertEquals('dummy', $this->fmr()->get('test'));

            $this->assertEquals(
                ['name' => 'test', 'type' => 'script'],
                $this->paired('name', 'test', 'type', 'script')
            );
        }
    }
