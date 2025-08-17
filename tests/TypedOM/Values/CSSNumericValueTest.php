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
		$this->assertEquals(123, $numericValue->getNumericValue());
		$this->assertEquals('number', $numericValue->getUnit());
	}

	public function testParseFloat()
	{
		$numericValue = CSSNumericValue::parse('123.45');
		$this->assertInstanceOf(CSSUnitValue::class, $numericValue);
		$this->assertEquals(123.45, $numericValue->getNumericValue());
		$this->assertEquals('number', $numericValue->getUnit());
	}

	public function testParsePixelValue()
	{
		$numericValue = CSSNumericValue::parse('42px');
		$this->assertInstanceOf(CSSUnitValue::class, $numericValue);
		$this->assertEquals(42, $numericValue->getNumericValue());
		$this->assertEquals('px', $numericValue->getUnit());
	}

	public function testParsePercentageValue()
	{
		$numericValue = CSSNumericValue::parse('80.5%');
		$this->assertInstanceOf(CSSUnitValue::class, $numericValue);
		$this->assertEquals(80.5, $numericValue->getNumericValue());
		$this->assertEquals('%', $numericValue->getUnit());
	}

	public function testParseEmValue()
	{
		$numericValue = CSSNumericValue::parse('1.2em');
		$this->assertInstanceOf(CSSUnitValue::class, $numericValue);
		$this->assertEquals(1.2, $numericValue->getNumericValue());
		$this->assertEquals('em', $numericValue->getUnit());
	}

	public function testParseWithWhitespace()
	{
		$numericValue = CSSNumericValue::parse('  -20rem  ');
		$this->assertInstanceOf(CSSUnitValue::class, $numericValue);
		$this->assertEquals(-20, $numericValue->getNumericValue());
		$this->assertEquals('rem', $numericValue->getUnit());
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
		$this->assertCount(2, $numericValue->getValues());
		$this->assertEquals('10px', $numericValue->getValues()[0]->toString());
		$this->assertEquals('5%', $numericValue->getValues()[1]->toString());

		// Simple product
		$numericValue = CSSNumericValue::parse('calc(10px * 2)');
		$this->assertInstanceOf(CSSMathProduct::class, $numericValue);
		$this->assertCount(2, $numericValue->getValues());
		$this->assertEquals('10px', $numericValue->getValues()[0]->toString());
		$this->assertEquals('2', $numericValue->getValues()[1]->toString());

		// More complex expression with precedence
		$numericValue = CSSNumericValue::parse('calc(10px + 5% * 2)');
		$this->assertInstanceOf(CSSMathSum::class, $numericValue);
		$this->assertCount(2, $numericValue->getValues());
		$this->assertEquals('10px', $numericValue->getValues()[0]->toString());
		$this->assertInstanceOf(CSSMathProduct::class, $numericValue->getValues()[1]);
		$this->assertEquals('5%', $numericValue->getValues()[1]->getValues()[0]->toString());
		$this->assertEquals('2', $numericValue->getValues()[1]->getValues()[1]->toString());

		// Expression with parentheses
		$numericValue = CSSNumericValue::parse('calc((10px + 5%) * 2)');
		$this->assertInstanceOf(CSSMathProduct::class, $numericValue);
		$this->assertCount(2, $numericValue->getValues());
		$this->assertInstanceOf(CSSMathSum::class, $numericValue->getValues()[0]);
		$this->assertEquals('10px', $numericValue->getValues()[0]->getValues()[0]->toString());
		$this->assertEquals('5%', $numericValue->getValues()[0]->getValues()[1]->toString());
		$this->assertEquals('2', $numericValue->getValues()[1]->toString());
	}
}
