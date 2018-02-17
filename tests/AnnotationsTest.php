<?php

use Octo\Annotation;

class AnnotationsTest extends TestCase
{
    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function testClass()
    {
        $annotations = Annotation::class(Annotation::class);

        $this->assertCount(1, $annotations);
        $this->assertSame('Octo', $annotations['package']);
    }

    /**
     * @throws Exception
     *
     * @throws ReflectionException
     */
    public function testMethod()
    {
        $annotations = Annotation::method(Annotation::class, 'method');

        $this->assertCount(3, $annotations);
        $this->assertCount(2, $annotations['param']);
        $this->assertSame('array', $annotations['return']);
    }
}