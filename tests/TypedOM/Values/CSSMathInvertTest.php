<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMathInvert;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class CSSMathInvertTest extends TestCase
{
    public function testToString()
    {
        $invert = new CSSMathInvert(new CSSUnitValue(10, 'px'));
        $this->assertEquals('calc(1 / 10px)', $invert->toString());
    }
}
