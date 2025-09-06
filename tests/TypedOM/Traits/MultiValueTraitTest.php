<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Traits;

use Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Traits\Fixtures\MultiValueTraitClass;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class MultiValueTraitTest extends TestCase
{
    public function testConstructorWithCSSNumericArray()
    {
        $numericArray = new CSSNumericArray([new CSSUnitValue(10, 'px'), new CSSUnitValue(20, 'px')]);
        $class = new MultiValueTraitClass($numericArray);
        $this->assertSame($numericArray, $class->values);
        $this->assertEquals('10px, 20px', (string)$class);
    }

    public function testConstructorWithCSSNumericValue()
    {
        $numericValue = new CSSUnitValue(10, 'px');
        $class = new MultiValueTraitClass($numericValue);
        $this->assertCount(1, $class->values);
        $this->assertEquals('10px', (string)$class);
    }

    public function testConstructorWithArray()
    {
        $array = [new CSSUnitValue(10, 'px'), new CSSUnitValue(20, 'px')];
        $class = new MultiValueTraitClass($array);
        $this->assertCount(2, $class->values);
        $this->assertEquals('10px, 20px', (string)$class);
    }

    public function testClone()
    {
        $array = [new CSSUnitValue(10, 'px'), new CSSUnitValue(20, 'px')];
        $class = new MultiValueTraitClass($array);
        $clone = $class->clone();
        $this->assertNotSame($class, $clone);
        $this->assertNotSame($class->values, $clone->values);
    }
}