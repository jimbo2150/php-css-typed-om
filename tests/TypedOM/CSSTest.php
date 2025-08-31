<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM;

use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitEnum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMin;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;
use PHPUnit\Framework\TestCase;
use BadMethodCallException;
use InvalidArgumentException;

class CSSTest extends TestCase
{
    public function testValidStaticUnitMethods()
    {
		$px = CSS::px("10");
        $this->assertInstanceOf(CSSUnitValue::class, $px);
		$this->assertEquals(new CSSUnitValue(10, CSSUnitEnum::LENGTH_px), $px);
		$this->assertEquals(null, $px->length);
		$num = CSS::number("35.42");
		$this->assertEquals(new CSSUnitValue(35.42, CSSUnitEnum::NUMBER), $num);
		$this->assertEquals(null, $num->length);
        $this->assertEquals(new CSSUnitValue(10, CSSUnitEnum::LENGTH_px), CSS::px("10"));
        $this->assertEquals(new CSSUnitValue(1.5, CSSUnitEnum::LENGTH_em), CSS::em("1.5"));
        $this->assertEquals(new CSSUnitValue(50, CSSUnitEnum::PERCENT), CSS::percent("50"));
    }

    public function testInvalidStaticUnitMethod()
    {
        $this->expectException(BadMethodCallException::class);
        CSS::invalidUnit("10");
    }

    public function testStaticUnitMethodWithNoArgument()
    {
        $this->expectException(InvalidArgumentException::class);
        CSS::px();
    }

    public function testStaticUnitMethodWithNonNumericArgument()
    {
        $this->expectException(InvalidArgumentException::class);
        CSS::px('abc');
    }

	public function testAddMethod()
    {
        $sum = CSS::px('10')->add(CSS::percent('10'))->add(CSS::flex('23'))->add(CSS::dppx('1324'));
		$this->assertInstanceOf(CSSMathSum::class, $sum);
		$this->assertEquals(4, $sum->length);
    }

	public function testMinMethod()
	   {
	       $min = CSS::px('10')->min(new CSSNumericArray([CSS::dppx('1324'), CSS::vh('2043')]));
		$this->assertInstanceOf(CSSMathMin::class, $min);
		$this->assertEquals(3, $min->length);
	   }

	public function testEscape()
	{
		// Test basic valid identifiers
		$this->assertEquals('hello', CSS::escape('hello'));
		$this->assertEquals('world', CSS::escape('world'));
		$this->assertEquals('_private', CSS::escape('_private'));
		$this->assertEquals('-webkit', CSS::escape('-webkit'));
		$this->assertEquals('a1b2', CSS::escape('a1b2'));
		$this->assertEquals('test-case', CSS::escape('test-case'));
		$this->assertEquals('test_case', CSS::escape('test_case'));

		// Test escaping first character if invalid start
		$this->assertEquals('\\\\31 23', CSS::escape('123'));
		$this->assertEquals('-\\\\31 23', CSS::escape('-123'));
		$this->assertEquals('_123', CSS::escape('_123'));

		// Test escaping special characters
		$this->assertEquals('hello\\\\ world', CSS::escape('hello world'));
		$this->assertEquals('test\\\\.', CSS::escape('test.'));
		$this->assertEquals('test\\\\#', CSS::escape('test#'));
		$this->assertEquals('test\\\\(', CSS::escape('test('));

		// Test Unicode
		$this->assertEquals('café', CSS::escape('café'));
		$this->assertEquals('测试', CSS::escape('测试'));
		$this->assertEquals('😀', CSS::escape('😀')); // Emoji, should be escaped

		// Test control characters
		$this->assertEquals('�', CSS::escape("\0"));
		$this->assertEquals('\\\\1', CSS::escape("\1"));
		$this->assertEquals('\\\\7f ', CSS::escape("\x7F"));
		$this->assertEquals('\\\\?', CSS::escape("\x3F"));

		// Test custom properties
		$this->assertEquals('--custom', CSS::escape('--custom'));
		$this->assertEquals('--custom-prop', CSS::escape('--custom-prop'));
		$this->assertEquals('--123', CSS::escape('--123')); // Valid for custom
		$this->assertEquals('--custom\\\\ prop', CSS::escape('--custom prop'));
		$this->assertEquals('--😀', CSS::escape('--😀'));

		// Test empty string
		$this->assertEquals('', CSS::escape(''));

		// Test single character
		$this->assertEquals('a', CSS::escape('a'));
		$this->assertEquals('\\\\31 ', CSS::escape('1'));
		$this->assertEquals('\\\\ ', CSS::escape(' '));

		// Examples from MDN: https://developer.mozilla.org/en-US/docs/Web/API/CSS/escape_static
		$this->assertEquals('\\\\.foo\\\\#bar', CSS::escape('.foo#bar'));
		$this->assertEquals('\\\\(\\\\)\\\\[\\\\]\\\\{\\\\}', CSS::escape('()[]{}'));
		$this->assertEquals('--a', CSS::escape('--a'));
		$this->assertEquals('\\\\30 ', CSS::escape(0));
		$this->assertEquals('�', CSS::escape('\0'));
	}

	public function testEscapeInvalidArgument()
	{
		$this->expectException(\TypeError::class);
		CSS::escape(123);
	}

	public function testEscapeNull()
	{
		$this->expectException(\TypeError::class);
		CSS::escape(null);
	}
}
