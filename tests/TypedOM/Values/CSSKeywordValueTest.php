<?php

declare(strict_types=1);

namespace Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSSKeywordValue class.
 */
class CSSKeywordValueTest extends TestCase
{
    public function testConstructor()
    {
        $keyword = new CSSKeywordValue('auto');
        $this->assertInstanceOf(CSSKeywordValue::class, $keyword);
    }

    public function testToString()
    {
        $keyword = new CSSKeywordValue('inherit');
        $this->assertSame('inherit', $keyword->toString());
    }

    public function testClone()
    {
        $keyword = new CSSKeywordValue('initial');
        $cloned = $keyword->clone();
        
        $this->assertInstanceOf(CSSKeywordValue::class, $cloned);
        $this->assertNotSame($keyword, $cloned);
        $this->assertSame($keyword->toString(), $cloned->toString());
    }

    public function testIsValid()
    {
        $keyword = new CSSKeywordValue('unset');
        $this->assertTrue($keyword->isValid());
    }

    public function testEmptyKeyword()
    {
        $keyword = new CSSKeywordValue('');
        $this->assertSame('', $keyword->toString());
    }
}