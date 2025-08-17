<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMathMax;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class CSSMathMaxTest extends TestCase
{
    public function testToString()
    {
        $max = new CSSMathMax(
            new CSSUnitValue(10, 'px'),
            new CSSUnitValue(5, '%')
        );
        $this->assertEquals('max(10px, 5%)', $max->toString());
    }
}
