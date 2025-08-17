<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSSkew;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class CSSSkewTest extends TestCase
{
    public function testToString()
    {
        $skew = new CSSSkew(
            new CSSUnitValue(10, 'deg'),
            new CSSUnitValue(20, 'deg')
        );
        $this->assertEquals('skew(10deg, 20deg)', $skew->toString());
    }
}
