<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSScale;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class CSSScaleTest extends TestCase
{
    public function testToString2D()
    {
        $scale = new CSSScale(
            new CSSUnitValue(2, 'number'),
            new CSSUnitValue(3, 'number')
        );
        $this->assertEquals('scale(2, 3)', $scale->toString());
    }

    public function testToString3D()
    {
        $scale = new CSSScale(
            new CSSUnitValue(2, 'number'),
            new CSSUnitValue(3, 'number'),
            new CSSUnitValue(0.5, 'number')
        );
        $this->assertEquals('scale3d(2, 3, 0.5)', $scale->toString());
    }
}
