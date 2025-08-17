<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSPerspective;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class CSSPerspectiveTest extends TestCase
{
    public function testToString()
    {
        $perspective = new CSSPerspective(new CSSUnitValue(1000, 'px'));
        $this->assertEquals('perspective(1000px)', $perspective->toString());
    }
}
