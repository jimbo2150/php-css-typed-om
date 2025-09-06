<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Parser;

use Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert;
use PHPUnit\Framework\TestCase;

class CSSCalcParserTest extends TestCase
{
    public function testParseSimpleAddition()
    {
        $result = CSSCalcParser::parse('calc(10px + 5px)');

        $this->assertInstanceOf(CSSMathSum::class, $result);
        /** @var CSSMathSum $result */
        $this->assertEquals(2, $result->length);
        $this->assertInstanceOf(CSSUnitValue::class, $result->inner_values[0]);
        $this->assertEquals(10, $result->inner_values[0]->value);
        $this->assertEquals('px', $result->inner_values[0]->unit);
        $this->assertInstanceOf(CSSUnitValue::class, $result->inner_values[1]);
        $this->assertEquals(5, $result->inner_values[1]->value);
        $this->assertEquals('px', $result->inner_values[1]->unit);
    }

    public function testParseSimpleSubtraction()
    {
        $result = CSSCalcParser::parse('calc(10px - 5px)');

        $this->assertInstanceOf(CSSMathSum::class, $result);
        /** @var CSSMathSum $result */
        $this->assertEquals(2, $result->length);
        $this->assertInstanceOf(CSSUnitValue::class, $result->inner_values[0]);
        $this->assertEquals(10, $result->inner_values[0]->value);
        $this->assertEquals('px', $result->inner_values[0]->unit);
        $this->assertInstanceOf(CSSMathNegate::class, $result->inner_values[1]);
        /** @var CSSMathNegate $negate */
        $negate = $result->inner_values[1];
        $this->assertEquals(1, $negate->length);
        $this->assertInstanceOf(CSSUnitValue::class, $negate->inner_values[0]);
        $this->assertEquals(5, $negate->inner_values[0]->value);
        $this->assertEquals('px', $negate->inner_values[0]->unit);
    }

    public function testParseSimpleMultiplication()
    {
        $result = CSSCalcParser::parse('calc(10px * 2)');

        $this->assertInstanceOf(CSSMathProduct::class, $result);
        /** @var CSSMathProduct $result */
        $this->assertEquals(2, $result->length);
        $this->assertInstanceOf(CSSUnitValue::class, $result->inner_values[0]);
        $this->assertEquals(10, $result->inner_values[0]->value);
        $this->assertEquals('px', $result->inner_values[0]->unit);
        $this->assertInstanceOf(CSSUnitValue::class, $result->inner_values[1]);
        $this->assertEquals(2, $result->inner_values[1]->value);
        $this->assertEquals('', $result->inner_values[1]->unit);
    }

    public function testParseSimpleDivision()
    {
        $result = CSSCalcParser::parse('calc(10px / 2)');

        $this->assertInstanceOf(CSSMathProduct::class, $result);
        /** @var CSSMathProduct $result */
        $this->assertEquals(2, $result->length);
        $this->assertInstanceOf(CSSUnitValue::class, $result->inner_values[0]);
        $this->assertEquals(10, $result->inner_values[0]->value);
        $this->assertEquals('px', $result->inner_values[0]->unit);
        $this->assertInstanceOf(CSSMathInvert::class, $result->inner_values[1]);
        /** @var CSSMathInvert $invert */
        $invert = $result->inner_values[1];
        $this->assertEquals(1, $invert->length);
        $this->assertInstanceOf(CSSUnitValue::class, $invert->inner_values[0]);
        $this->assertEquals(2, $invert->inner_values[0]->value);
        $this->assertEquals('', $invert->inner_values[0]->unit);
    }

    public function testParseMixedOperations()
    {
        $result = CSSCalcParser::parse('calc(10px + 5px * 2)');

        $this->assertInstanceOf(CSSMathSum::class, $result);
        /** @var CSSMathSum $result */
        $this->assertEquals(2, $result->length);
        $this->assertInstanceOf(CSSUnitValue::class, $result->inner_values[0]);
        $this->assertEquals(10, $result->inner_values[0]->value);
        $this->assertInstanceOf(CSSMathProduct::class, $result->inner_values[1]);
    }

    public function testParseWithParentheses()
    {
        $result = CSSCalcParser::parse('calc((10px + 5px) * 2)');

        $this->assertInstanceOf(CSSMathProduct::class, $result);
        /** @var CSSMathProduct $result */
        $this->assertEquals(2, $result->length);
        $this->assertInstanceOf(CSSMathSum::class, $result->inner_values[0]);
        $this->assertInstanceOf(CSSUnitValue::class, $result->inner_values[1]);
        $this->assertEquals(2, $result->inner_values[1]->value);
    }

    public function testParseDifferentUnits()
    {
        $result = CSSCalcParser::parse('calc(10px + 5em)');

        $this->assertInstanceOf(CSSMathSum::class, $result);
        /** @var CSSMathSum $result */
        $this->assertEquals(2, $result->length);
        $this->assertEquals('px', $result->inner_values[0]->unit);
        $this->assertEquals('em', $result->inner_values[1]->unit);
    }

    public function testParseNegativeNumbers()
    {
        $result = CSSCalcParser::parse('calc(-10px + 5px)');

        $this->assertInstanceOf(CSSMathSum::class, $result);
        /** @var CSSMathSum $result */
        $this->assertEquals(2, $result->length);
        $this->assertEquals(-10, $result->inner_values[0]->value);
        $this->assertEquals(5, $result->inner_values[1]->value);
    }

    public function testParseDecimalNumbers()
    {
        $result = CSSCalcParser::parse('calc(1.5px * 2.5)');

        $this->assertInstanceOf(CSSMathProduct::class, $result);
        /** @var CSSMathProduct $result */
        $this->assertEquals(2, $result->length);
        $this->assertEquals(1.5, $result->inner_values[0]->value);
        $this->assertEquals(2.5, $result->inner_values[1]->value);
    }

	public function testParseComplex()
	   {
	       $result = CSSCalcParser::parse('calc(1.5px * 2.5dvw + (2px - 5%))');

	       $this->assertInstanceOf(CSSMathSum::class, $result);
	       /** @var CSSMathSum $result */
	       $this->assertEquals(2, $result->length);

	       // First part: 1.5px * 2.5dvw
	       $this->assertInstanceOf(CSSMathProduct::class, $result->inner_values[0]);
	       /** @var CSSMathProduct $product */
	       $product = $result->inner_values[0];
	       $this->assertEquals(2, $product->length);
	       $this->assertInstanceOf(CSSUnitValue::class, $product->inner_values[0]);
	       $this->assertEquals(1.5, $product->inner_values[0]->value);
	       $this->assertEquals('px', $product->inner_values[0]->unit);
	       $this->assertInstanceOf(CSSUnitValue::class, $product->inner_values[1]);
	       $this->assertEquals(2.5, $product->inner_values[1]->value);
	       $this->assertEquals('dvw', $product->inner_values[1]->unit);

	       // Second part: (2px - 5%)
	       $this->assertInstanceOf(CSSMathSum::class, $result->inner_values[1]);
	       /** @var CSSMathSum $sum */
	       $sum = $result->inner_values[1];
	       $this->assertEquals(2, $sum->length);
	       $this->assertInstanceOf(CSSUnitValue::class, $sum->inner_values[0]);
	       $this->assertEquals(2, $sum->inner_values[0]->value);
	       $this->assertEquals('px', $sum->inner_values[0]->unit);
	       $this->assertInstanceOf(CSSMathNegate::class, $sum->inner_values[1]);
	       /** @var CSSMathNegate $negate */
	       $negate = $sum->inner_values[1];
	       $this->assertEquals(1, $negate->length);
	       $this->assertInstanceOf(CSSUnitValue::class, $negate->inner_values[0]);
	       $this->assertEquals(5, $negate->inner_values[0]->value);
	       $this->assertEquals('%', $negate->inner_values[0]->unit);
	   }

    public function testParseSingleValue()
    {
        $result = CSSCalcParser::parse('calc(10px)');

        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertEquals(10, $result->value);
        $this->assertEquals('px', $result->unit);
    }

    public function testParseMismatchedParentheses()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Mismatched parentheses');
        CSSCalcParser::parse('calc((10px + 5px)');
    }

    public function testParseUnknownToken()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown token: @');
        CSSCalcParser::parse('calc(10px + @)');
    }

    public function testParseInsufficientOperands()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient operands');
        CSSCalcParser::parse('calc(10px +)');
    }

    public function testParseInvalidExpression()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient operands for operator: +');
        CSSCalcParser::parse('calc(+)');
    }

    public function testParseInvalidExpression2()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid expression.');
        CSSCalcParser::parse('calc(10px 5px)');
    }

    public function testParseMismatchedParenthesesClosing()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Mismatched parentheses.');
        CSSCalcParser::parse('calc(10px + 5px))');
    }

    public function testParseComplexExpression()
    {
        $result = CSSCalcParser::parse('calc((10px + 5em) * 2 - 3px)');

        $this->assertInstanceOf(CSSMathSum::class, $result);
        /** @var CSSMathSum $result */
        $this->assertEquals(2, $result->length);
        $this->assertInstanceOf(CSSMathProduct::class, $result->inner_values[0]);
        $this->assertInstanceOf(CSSMathNegate::class, $result->inner_values[1]);
    }

    /**
     * Test parse with extra whitespace around operators.
     */
    public function testParseWithExtraWhitespace()
    {
        $result = CSSCalcParser::parse('calc( 10px  +  5px )');

        $this->assertInstanceOf(CSSMathSum::class, $result);
        /** @var CSSMathSum $result */
        $this->assertEquals(2, $result->length);
        $this->assertEquals(10, $result->inner_values[0]->value);
        $this->assertEquals('px', $result->inner_values[0]->unit);
        $this->assertEquals(5, $result->inner_values[1]->value);
        $this->assertEquals('px', $result->inner_values[1]->unit);
    }

    /**
     * Test parse with negative decimal numbers.
     */
    public function testParseNegativeDecimal()
    {
        $result = CSSCalcParser::parse('calc(-1.5px * 2)');

        $this->assertInstanceOf(CSSMathProduct::class, $result);
        /** @var CSSMathProduct $result */
        $this->assertEquals(2, $result->length);
        $this->assertEquals(-1.5, $result->inner_values[0]->value);
        $this->assertEquals('px', $result->inner_values[0]->unit);
        $this->assertEquals(2, $result->inner_values[1]->value);
        $this->assertEquals('', $result->inner_values[1]->unit);
    }

    /**
     * Test parse with mixed whitespace in complex expression.
     */
    public function testParseComplexWithWhitespace()
    {
        $result = CSSCalcParser::parse('calc( ( 10px + 5em ) * 2 - 3px )');

        $this->assertInstanceOf(CSSMathSum::class, $result);
        /** @var CSSMathSum $result */
        $this->assertEquals(2, $result->length);
        $this->assertInstanceOf(CSSMathProduct::class, $result->inner_values[0]);
        $this->assertInstanceOf(CSSMathNegate::class, $result->inner_values[1]);
    }
    public function testParseEmptyCalc()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid expression.');
        CSSCalcParser::parse('calc()');
    }

    public function testParseWhitespaceCalc()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid expression.');
        CSSCalcParser::parse('calc( )');
    }

    public function testParseWithMultipleSpaces()
    {
        $result = CSSCalcParser::parse('calc(10px  +  5px)');
        $this->assertInstanceOf(CSSMathSum::class, $result);
    }
}