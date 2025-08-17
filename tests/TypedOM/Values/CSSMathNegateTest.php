<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMathNegate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class CSSMathNegateTest extends TestCase
{
    public function testToString()
    {
        $negate = new CSSMathNegate(new CSSUnitValue(10, 'px'));
        $this->assertEquals('calc(-1 * 10px)', $negate->toString());
    }
}
