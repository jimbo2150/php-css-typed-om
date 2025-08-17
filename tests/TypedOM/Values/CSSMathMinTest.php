<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMathMin;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class CSSMathMinTest extends TestCase
{
    public function testToString()
    {
        $min = new CSSMathMin(
            new CSSUnitValue(10, 'px'),
            new CSSUnitValue(5, '%')
        );
        $this->assertEquals('min(10px, 5%)', $min->toString());
    }
}
