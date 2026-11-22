<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM;

use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPropertyRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
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
        $this->assertEquals(new CSSUnitValue(10, CSSUnitEnum::LENGTH_px), CSS::px(10));
        $this->assertEquals(new CSSUnitValue(10.5, CSSUnitEnum::LENGTH_px), CSS::px(10.5));
    }

    public function testInvalidStaticUnitMethod()
    {
        $this->expectException(BadMethodCallException::class);
        CSS::invalidUnit("10");
    }

    public function testStaticUnitMethodWithNoArgument()
    {
        $this->expectException(InvalidArgumentException::class);
		// @phpstan-ignore-next-line
		// phpcs:ignore
		CSS::px();
    }

    public function testStaticUnitMethodWithNonNumericArgument()
    {
        $this->expectException(InvalidArgumentException::class);
        CSS::px('abc');
    }

	public function testAddMethod()
    {
		$sum = CSS::px('10')->add(CSS::em('10'))->add(CSS::vh('23'));
		$this->assertInstanceOf(CSSMathSum::class, $sum);
		$this->assertEquals(3, $sum->length);
    }

	public function testMinMethod()
	{
	    $min = CSS::px('10')->min(new CSSNumericArray([CSS::em('1324'), CSS::vh('2043')]));
		$this->assertInstanceOf(CSSMathMin::class, $min);
		$this->assertEquals(3, $min->length);
	}

	public function testArithmeticRejectsIncompatibleDimensions()
	{
		$this->expectException(InvalidArgumentException::class);
		CSS::px(10)->add(CSS::s(1));
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
		$this->assertEquals('\31 23', CSS::escape('123'));
		$this->assertEquals('-\31 23', CSS::escape('-123'));
		$this->assertEquals('_123', CSS::escape('_123'));
		$this->assertEquals('\-', CSS::escape('-'));

		// Test floats
		$this->assertEquals('\30 \.25', CSS::escape(00.25));
		$this->assertEquals('-\31 \.235', CSS::escape(-1.235));
		$this->assertEquals('-\31 23', CSS::escape(-123.0));

		// Test escaping special characters
		$this->assertEquals('hello\ world', CSS::escape('hello world'));
		$this->assertEquals('test\.', CSS::escape('test.'));
		$this->assertEquals('test\#', CSS::escape('test#'));
		$this->assertEquals('test\(', CSS::escape('test('));
		$this->assertEquals('a\"b', CSS::escape('a"b'));

		// Test Unicode
		$this->assertEquals('café', CSS::escape('café'));
		$this->assertEquals('测试', CSS::escape('测试'));
		$this->assertEquals('😀', CSS::escape('😀'));

		// Test control characters and null bytes
		$this->assertEquals("\u{FFFD}", CSS::escape("\0"));
		$this->assertEquals("prefix\u{FFFD}suffix", CSS::escape("prefix\x00suffix"));
		$this->assertEquals('\\\\0', CSS::escape('\0'));
		$this->assertEquals('\\\\0', CSS::escape('\\0'));
		$this->assertEquals('\1 ', CSS::escape("\1"));
		$this->assertEquals('\7f ', CSS::escape("\x7F"));
		$this->assertEquals('\?', CSS::escape("\x3F"));
		$this->assertEquals("\u{FFFD}", CSS::escape("\xFF"));

		// Test custom properties
		$this->assertEquals('--custom', CSS::escape('--custom'));
		$this->assertEquals('--custom-prop', CSS::escape('--custom-prop'));
		$this->assertEquals('--123', CSS::escape('--123')); // Valid for custom
		$this->assertEquals('--custom\ prop', CSS::escape('--custom prop'));
		$this->assertEquals('--😀', CSS::escape('--😀'));

		// Test empty string
		$this->assertEquals('', CSS::escape(''));

		// Test single character
		$this->assertEquals('a', CSS::escape('a'));
		$this->assertEquals('\31 ', CSS::escape('1'));
		$this->assertEquals('\ ', CSS::escape(' '));

		// Examples from MDN: https://developer.mozilla.org/en-US/docs/Web/API/CSS/escape_static
		$this->assertEquals('\.foo\#bar', CSS::escape('.foo#bar'));
		$this->assertEquals('\(\)\[\]\{\}', CSS::escape('()[]{}'));
		$this->assertEquals('--a', CSS::escape('--a'));
		$this->assertEquals('\30 ', CSS::escape(0));
	}

	public function testEscapeNull()
	{
		$this->assertEquals("\u{FFFD}", CSS::escape(null));
	}

	public function testResolutionUnitXAndRejectsInvalidDimensionSyntax(): void
	{
		$x = CSS::x(2);
		self::assertSame('x', $x->unit);
		self::assertSame('2x', (string) $x);
		self::assertEqualsWithDelta(2.0, $x->to('dppx')->value, 1e-9);
		self::assertEqualsWithDelta(192.0, $x->to('dpi')->value, 1e-9);
		self::assertSame('x', CSSNumericValue::parse('2x')->unit);

		// Invalid CSS dimension syntax like `10number` / `50percent` / `1flex` must be rejected
		foreach (['10number', '50percent', '1flex'] as $invalid) {
			try {
				CSSNumericValue::parse($invalid);
				self::fail('Expected invalid CSS numeric syntax to throw: ' . $invalid);
			} catch (InvalidArgumentException) {
				self::assertTrue(true);
			}
		}
	}

	public function testRegisterPropertyLifecycleAndValidation(): void
	{
		CSS::clearRegisteredProperties();
		try {
			$rule = CSS::registerProperty([
				'name' => '--brand-gap',
				'syntax' => '<length>',
				'inherits' => false,
				'initialValue' => '16px',
			]);
			self::assertInstanceOf(CSSPropertyRule::class, $rule);
			self::assertSame('--brand-gap', $rule->name);
			self::assertSame('<length>', $rule->syntax);
			self::assertFalse($rule->inherits);
			self::assertSame('16px', $rule->initialValue);
			self::assertSame($rule, CSS::getRegisteredProperty('--brand-gap'));
			self::assertCount(1, CSS::getRegisteredProperties());

			// Universal syntax allows null initialValue
			$universal = CSS::registerProperty([
				'name' => '--any-token',
				'inherits' => true,
			]);
			self::assertSame('*', $universal->syntax);
			self::assertNull($universal->initialValue);

			// Duplicate registration throws
			try {
				CSS::registerProperty(['name' => '--brand-gap', 'inherits' => false]);
				self::fail('Expected duplicate registration to throw.');
			} catch (InvalidArgumentException) {
				self::assertTrue(true);
			}

			// var() in initialValue is rejected
			try {
				CSS::registerProperty([
					'name' => '--bad-var',
					'syntax' => '<length>',
					'inherits' => false,
					'initialValue' => 'var(--brand-gap)',
				]);
				self::fail('Expected var() in initialValue to throw.');
			} catch (InvalidArgumentException) {
				self::assertTrue(true);
			}

			// CSSPropertyRule input is validated identically
			$badRule = new CSSPropertyRule('--bad-rule', new CSSStyleDeclaration('syntax: "<length>"; inherits: maybe;'));
			try {
				CSS::registerProperty($badRule);
				self::fail('Expected invalid CSSPropertyRule to throw.');
			} catch (InvalidArgumentException) {
				self::assertTrue(true);
			}

			self::assertTrue(CSS::unregisterProperty('--brand-gap'));
			self::assertNull(CSS::getRegisteredProperty('--brand-gap'));
			self::assertFalse(CSS::unregisterProperty('--brand-gap'));
		} finally {
			CSS::clearRegisteredProperties();
		}
	}

	public function testSupportsTwoArgAndConditionalOneArg(): void
	{
		// 2-arg form
		self::assertTrue(CSS::supports('display', 'grid'));
		self::assertTrue(CSS::supports('--custom', '10px'));
		self::assertFalse(CSS::supports('display', 'grid !important'));
		self::assertFalse(CSS::supports('not-a-real-css-prop', '1'));
		self::assertFalse(CSS::supports('display', ''));

		// 1-arg form: bare declarations, parenthesized, !important, boolean combinators, selector()
		self::assertTrue(CSS::supports('display: grid'));
		self::assertTrue(CSS::supports('(display: grid !important)'));
		self::assertTrue(CSS::supports('not (not-a-real-prop: 1)'));
		self::assertTrue(CSS::supports('(display: grid) and (color: red)'));
		self::assertTrue(CSS::supports('(not-a-real-prop: 1) or (display: flex)'));
		self::assertTrue(CSS::supports('selector(:has(> img))'));

		// Malformed or disallowed combinations return false without throwing
		self::assertFalse(CSS::supports('not(display: grid)'));
		self::assertFalse(CSS::supports('(display: grid) and (color: red) or (width: 1px)'));
		self::assertFalse(CSS::supports('selector()'));
		self::assertFalse(CSS::supports(str_repeat('(', 40) . 'display: grid' . str_repeat(')', 40), null, new CSSParserOptions(maxDepth: 5)));
	}
}
