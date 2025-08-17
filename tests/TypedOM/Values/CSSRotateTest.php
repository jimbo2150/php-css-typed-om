<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSRotate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class CSSRotateTest extends TestCase
{
    public function testToString2D()
    {
        $rotate = new CSSRotate(new CSSUnitValue(45, 'deg'));
        $this->assertEquals('rotate(45deg)', $rotate->toString());
    }

    public function testToString3D()
    {
        $rotate = new CSSRotate(
            new CSSUnitValue(1, 'number'),
            new CSSUnitValue(2, 'number'),
            new CSSUnitValue(3, 'number'),
            new CSSUnitValue(45, 'deg')
        );
        $this->assertEquals('rotate3d(1, 2, 3, 45deg)', $rotate->toString());
    }
}
