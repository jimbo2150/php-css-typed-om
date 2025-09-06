<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Traits;

use Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Traits\Fixtures\SimpleValueTraitClass;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class SimpleValueTraitTest extends TestCase
{
    public function testSetValueWithValidValue()
    {
        $class = new SimpleValueTraitClass(10);
        $this->assertEquals(10, $class->value);
    }

    public function testSetValueWithInvalidValue()
    {
        $this->expectException(InvalidArgumentException::class);
        new SimpleValueTraitClass('invalid');
    }

    public function testClone()
    {
        $class = new SimpleValueTraitClass(10);
        $clone = $class->clone();
        $this->assertNotSame($class, $clone);
        $this->assertEquals($class->value, $clone->value);
    }

    public function testToString()
    {
        $class = new SimpleValueTraitClass(10);
        $this->assertEquals('10', (string)$class);
    }
}