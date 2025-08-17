<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSVariableReferenceValue;
use PHPUnit\Framework\TestCase;

class CSSUnparsedValueTest extends TestCase
{
    public function testToStringWithStrings()
    {
        $unparsed = new CSSUnparsedValue(['10px', ' ', 'solid', ' ', 'red']);
        $this->assertEquals('10px solid red', $unparsed->toString());
    }

    public function testToStringWithVariableReference()
    {
        $unparsed = new CSSUnparsedValue([
            '1px solid ',
            new CSSVariableReferenceValue('--my-color')
        ]);
        $this->assertEquals('1px solid var(--my-color)', $unparsed->toString());
    }
}
