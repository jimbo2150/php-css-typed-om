<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMathSum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class CSSNumericValueTest extends TestCase
{
	public function testParseInteger()
	{
		$numericValue = CSSNumericValue::parse('123');
		$this->assertInstanceOf(CSSUnitValue::class, $numericValue);
		$this->assertEquals(123, $numericValue->value);
		$this->assertEquals('number', $numericValue->unit);
	}

	public function testParseFloat()
	{
		$numericValue = CSSNumericValue::parse('123.45');
		$this->assertInstanceOf(CSSUnitValue::class, $numericValue);
		$this->assertEquals(123.45, $numericValue->value);
		$this->assertEquals('number', $numericValue->unit);
	}

	public function testParsePixelValue()
	{
		$numericValue = CSSNumericValue::parse('42px');
		$this->assertInstanceOf(CSSUnitValue::class, $numericValue);
		$this->assertEquals(42, $numericValue->value);
		$this->assertEquals('px', $numericValue->unit);
	}

	public function testParsePercentageValue()
	{
		$numericValue = CSSNumericValue::parse('80.5%');
		$this->assertInstanceOf(CSSUnitValue::class, $numericValue);
		$this->assertEquals(80.5, $numericValue->value);
		$this->assertEquals('%', $numericValue->unit);
	}

	public function testParseEmValue()
	{
		$numericValue = CSSNumericValue::parse('1.2em');
		$this->assertInstanceOf(CSSUnitValue::class, $numericValue);
		$this->assertEquals(1.2, $numericValue->value);
		$this->assertEquals('em', $numericValue->unit);
	}

	public function testParseWithWhitespace()
	{
		$numericValue = CSSNumericValue::parse('  -20rem  ');
		$this->assertInstanceOf(CSSUnitValue::class, $numericValue);
		$this->assertEquals(-20, $numericValue->value);
		$this->assertEquals('rem', $numericValue->unit);
	}

	public function testParseInvalidValue()
	{
		$this->expectException(\InvalidArgumentException::class);
		CSSNumericValue::parse('not-a-number');
	}

	public function testParseCalc()
	{
		// Simple sum
		$numericValue = CSSNumericValue::parse('calc(10px + 5%)');
		$this->assertInstanceOf(CSSMathSum::class, $numericValue);
		$this->assertCount(2, $numericValue->values);
		$this->assertEquals('10px', $numericValue->values[0]->toString());
		$this->assertEquals('5%', $numericValue->values[1]->toString());

		// Simple product
		$numericValue = CSSNumericValue::parse('calc(10px * 2)');
		$this->assertInstanceOf(CSSMathProduct::class, $numericValue);
		$this->assertCount(2, $numericValue->values);
		$this->assertEquals('10px', $numericValue->values[0]->toString());
		$this->assertEquals('2', $numericValue->values[1]->toString());

		// More complex expression with precedence
		$numericValue = CSSNumericValue::parse('calc(10px + 5% * 2)');
		$this->assertInstanceOf(CSSMathSum::class, $numericValue);
		$this->assertCount(2, $numericValue->values);
		$this->assertEquals('10px', $numericValue->values[0]->toString());
		$this->assertInstanceOf(CSSMathProduct::class, $numericValue->values[1]);
		$this->assertEquals('5%', $numericValue->values[1]->values[0]->toString());
		$this->assertEquals('2', $numericValue->values[1]->values[1]->toString());

		// Expression with parentheses
		$numericValue = CSSNumericValue::parse('calc((10px + 5%) * 2)');
		$this->assertInstanceOf(CSSMathProduct::class, $numericValue);
		$this->assertCount(2, $numericValue->values);
		$this->assertInstanceOf(CSSMathSum::class, $numericValue->values[0]);
		$this->assertEquals('10px', $numericValue->values[0]->values[0]->toString());
		$this->assertEquals('5%', $numericValue->values[0]->values[1]->toString());
		$this->assertEquals('2', $numericValue->values[1]->toString());
	}
}
