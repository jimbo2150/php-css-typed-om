<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Parser;

use Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitEnum;
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
        $input = 'calc(10px + 5px)';
        $result = CSSCalcParser::parse($input);

        $this->assertInstanceOf(CSSMathSum::class, $result);
        /** @var CSSMathSum $result */
        $this->assertEquals(2, $result->length);
        $this->assertInstanceOf(CSSUnitValue::class, $result->inner_values[0]);
        $this->assertEquals(10, $result->inner_values[0]->value);
        $this->assertEquals('px', $result->inner_values[0]->unit);
        $this->assertInstanceOf(CSSUnitValue::class, $result->inner_values[1]);
        $this->assertEquals(5, $result->inner_values[1]->value);
        $this->assertEquals('px', $result->inner_values[1]->unit);
        $this->assertEquals($input, (string) $result);
    }

    public function testParseSimpleSubtraction()
    {
        $input = 'calc(10px - 5px)';
        $result = CSSCalcParser::parse($input);

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
        $this->assertEquals($input, (string) $result);
    }

    public function testParseSimpleMultiplication()
    {
        $input = 'calc(10px * 2)';
        $result = CSSCalcParser::parse($input);

        $this->assertInstanceOf(CSSMathProduct::class, $result);
        /** @var CSSMathProduct $result */
        $this->assertEquals(2, $result->length);
        $this->assertInstanceOf(CSSUnitValue::class, $result->inner_values[0]);
        $this->assertEquals(10, $result->inner_values[0]->value);
        $this->assertEquals('px', $result->inner_values[0]->unit);
        $this->assertInstanceOf(CSSUnitValue::class, $result->inner_values[1]);
        $this->assertEquals(2, $result->inner_values[1]->value);
        $this->assertEquals(CSSUnitEnum::NUMBER->value, $result->inner_values[1]->unit);
        $this->assertEquals($input, (string) $result);
    }

    public function testParseSimpleDivision()
    {
        $input = 'calc(10px / 2)';
        $result = CSSCalcParser::parse($input);

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
        $this->assertEquals(CSSUnitEnum::NUMBER->value, $invert->inner_values[0]->unit);
        $this->assertEquals($input, (string) $result);
    }

    public function testParseMixedOperations()
    {
        $input = 'calc(10px + 5px * 2)';
        $result = CSSCalcParser::parse($input);

        $this->assertInstanceOf(CSSMathSum::class, $result);
        /** @var CSSMathSum $result */
        $this->assertEquals(2, $result->length);
        $this->assertInstanceOf(CSSUnitValue::class, $result->inner_values[0]);
        $this->assertEquals(10, $result->inner_values[0]->value);
        $this->assertInstanceOf(CSSMathProduct::class, $result->inner_values[1]);
        $this->assertEquals($input, (string) $result);
    }

    public function testParseWithParentheses()
    {
		$input = 'calc((10px + 5px) * 2)';
        $result = CSSCalcParser::parse($input);

        $this->assertInstanceOf(CSSMathProduct::class, $result);
        /** @var CSSMathProduct $result */
        $this->assertEquals(2, $result->length);
        $this->assertInstanceOf(CSSMathSum::class, $result->inner_values[0]);
        $this->assertInstanceOf(CSSUnitValue::class, $result->inner_values[1]);
        $this->assertEquals(2, $result->inner_values[1]->value);
		$this->assertEquals($input, (string) $result);
    }

    public function testParseDifferentUnits()
    {
        $input = 'calc(10px + 5em)';
        $result = CSSCalcParser::parse($input);

        $this->assertInstanceOf(CSSMathSum::class, $result);
        /** @var CSSMathSum $result */
        $this->assertEquals(2, $result->length);
        $this->assertEquals('px', $result->inner_values[0]->unit);
        $this->assertEquals('em', $result->inner_values[1]->unit);
        $this->assertEquals($input, (string) $result);
    }

    public function testParseNegativeNumbers()
    {
        $input = 'calc(-10px + 5px)';
        $result = CSSCalcParser::parse($input);

        $this->assertInstanceOf(CSSMathSum::class, $result);
        /** @var CSSMathSum $result */
        $this->assertEquals(2, $result->length);
        $this->assertEquals(-10, $result->inner_values[0]->value);
        $this->assertEquals(5, $result->inner_values[1]->value);
        $this->assertEquals($input, (string) $result);
    }

    public function testParseDecimalNumbers()
    {
        $input = 'calc(1.5px * 2.5)';
        $result = CSSCalcParser::parse($input);

        $this->assertInstanceOf(CSSMathProduct::class, $result);
        /** @var CSSMathProduct $result */
        $this->assertEquals(2, $result->length);
        $this->assertEquals(1.5, $result->inner_values[0]->value);
        $this->assertEquals(2.5, $result->inner_values[1]->value);
        $this->assertEquals($input, (string) $result);
    }

	public function testParseComplex()
	   {
	       $input = 'calc(1.5px * 2.5dvw + (2px - 5%))';
	       $result = CSSCalcParser::parse($input);

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
	       $this->assertEquals($input, (string) $result);
	   }

    public function testParseSingleValue()
    {
        $input = 'calc(10px)';
		$output = '10px';
        $result = CSSCalcParser::parse($input);

        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertEquals(10, $result->value);
        $this->assertEquals('px', $result->unit);
        $this->assertEquals($output, (string) $result);
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
        $input = 'calc((10px + 5em) * 2 - 3px)';
        $result = CSSCalcParser::parse($input);

        $this->assertInstanceOf(CSSMathSum::class, $result);
        /** @var CSSMathSum $result */
        $this->assertEquals(2, $result->length);
        $this->assertInstanceOf(CSSMathProduct::class, $result->inner_values[0]);
        $this->assertInstanceOf(CSSMathNegate::class, $result->inner_values[1]);
        $this->assertEquals($input, (string) $result);
    }

    /**
     * Test parse with extra whitespace around operators.
     */
    public function testParseWithExtraWhitespace()
    {
        $input = 'calc( 10px  +  5px )';
        $result = CSSCalcParser::parse($input);

        $this->assertInstanceOf(CSSMathSum::class, $result);
        /** @var CSSMathSum $result */
        $this->assertEquals(2, $result->length);
        $this->assertEquals(10, $result->inner_values[0]->value);
        $this->assertEquals('px', $result->inner_values[0]->unit);
        $this->assertEquals(5, $result->inner_values[1]->value);
        $this->assertEquals('px', $result->inner_values[1]->unit);
        $this->assertEquals('calc(10px + 5px)', (string) $result);
    }

    /**
     * Test parse with negative decimal numbers.
     */
    public function testParseNegativeDecimal()
    {
        $input = 'calc(-1.5px * 2)';
        $result = CSSCalcParser::parse($input);

        $this->assertInstanceOf(CSSMathProduct::class, $result);
        /** @var CSSMathProduct $result */
        $this->assertEquals(2, $result->length);
        $this->assertEquals(-1.5, $result->inner_values[0]->value);
        $this->assertEquals('px', $result->inner_values[0]->unit);
        $this->assertEquals(2, $result->inner_values[1]->value);
        $this->assertEquals(CSSUnitEnum::NUMBER->value, $result->inner_values[1]->unit);
        $this->assertEquals($input, (string) $result);
    }

    /**
     * Test parse with mixed whitespace in complex expression.
     */
    public function testParseComplexWithWhitespace()
    {
        $input = 'calc( ( 10px + 5em ) * 2 - 3px )';
        $result = CSSCalcParser::parse($input);

        $this->assertInstanceOf(CSSMathSum::class, $result);
        /** @var CSSMathSum $result */
        $this->assertEquals(2, $result->length);
        $this->assertInstanceOf(CSSMathProduct::class, $result->inner_values[0]);
        $this->assertInstanceOf(CSSMathNegate::class, $result->inner_values[1]);
        $this->assertEquals('calc((10px + 5em) * 2 - 3px)', (string) $result);
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
        $input = 'calc(10px  +  5px)';
        $result = CSSCalcParser::parse($input);
        $this->assertInstanceOf(CSSMathSum::class, $result);
        $this->assertEquals('calc(10px + 5px)', (string) $result);
    }
}