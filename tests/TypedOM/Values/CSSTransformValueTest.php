<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTransformValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTranslate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class CSSTransformValueTest extends TestCase
{
    public function testToString()
    {
        $transform = new CSSTransformValue([
            new CSSTranslate(new CSSUnitValue(10, 'px'), new CSSUnitValue(20, 'px'))
        ]);
        $this->assertEquals('translate(10px, 20px)', $transform->toString());
    }

    public function testToString3D()
    {
        $transform = new CSSTransformValue([
            new CSSTranslate(
                new CSSUnitValue(10, 'px'),
                new CSSUnitValue(20, 'px'),
                new CSSUnitValue(5, 'px')
            )
        ]);
        $this->assertEquals('translate3d(10px, 20px, 5px)', $transform->toString());
    }
}
