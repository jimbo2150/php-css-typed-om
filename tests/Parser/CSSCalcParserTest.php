<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Parser;

use Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;
use PHPUnit\Framework\TestCase;

class CSSCalcParserTest extends TestCase
{
	public function testParseSimpleAddition(): void
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

	public function testParseSimpleSubtraction(): void
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

	public function testParseSimpleMultiplication(): void
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
		$this->assertEquals('number', $result->inner_values[1]->unit);
		$this->assertEquals($input, (string) $result);
	}

	public function testParseSimpleDivision(): void
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
		$this->assertEquals('number', $invert->inner_values[0]->unit);
		$this->assertEquals($input, (string) $result);
	}

	public function testParseMixedOperations(): void
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

	public function testParseWithParentheses(): void
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

	public function testParseDifferentUnits(): void
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

	public function testParseNegativeNumbers(): void
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

	public function testParseDecimalNumbers(): void
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

	public function testParseComplex(): void
	{
		$input = 'calc(1.5 * 2.5dvw + (2px - 5%))';
		$result = CSSCalcParser::parse($input);

		$this->assertInstanceOf(CSSMathSum::class, $result);
		/** @var CSSMathSum $result */
		$this->assertEquals(2, $result->length);

		// First part: 1.5 * 2.5dvw
		$this->assertInstanceOf(CSSMathProduct::class, $result->inner_values[0]);
		/** @var CSSMathProduct $product */
		$product = $result->inner_values[0];
		$this->assertEquals(2, $product->length);
		$this->assertInstanceOf(CSSUnitValue::class, $product->inner_values[0]);
		$this->assertEquals(1.5, $product->inner_values[0]->value);
		$this->assertEquals('number', $product->inner_values[0]->unit);
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
		$this->assertEquals('percent', $negate->inner_values[0]->unit);
		$this->assertEquals($input, (string) $result);
	}

	public function testParseSingleValue(): void
	{
		$input = 'calc(10px)';
		$output = '10px';
		$result = CSSCalcParser::parse($input);

		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals(10, $result->value);
		$this->assertEquals('px', $result->unit);
		$this->assertEquals($output, (string) $result);
	}

	public function testParseMismatchedParentheses(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Mismatched parentheses');
		CSSCalcParser::parse('calc((10px + 5px)');
	}

	public function testParseUnknownToken(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Unknown token: @');
		CSSCalcParser::parse('calc(10px + @)');
	}

	public function testParseInsufficientOperands(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Insufficient operands');
		CSSCalcParser::parse('calc(10px +)');
	}

	public function testParseInvalidExpression(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Insufficient operands for operator: +');
		CSSCalcParser::parse('calc(+)');
	}

	public function testParseInvalidExpression2(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid expression.');
		CSSCalcParser::parse('calc(10px 5px)');
	}

	public function testParseMismatchedParenthesesClosing(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Mismatched parentheses.');
		CSSCalcParser::parse('calc(10px + 5px))');
	}

	public function testParseComplexExpression(): void
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
	public function testParseWithExtraWhitespace(): void
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
	public function testParseNegativeDecimal(): void
	{
		$input = 'calc(-1.5px * 2)';
		$result = CSSCalcParser::parse($input);

		$this->assertInstanceOf(CSSMathProduct::class, $result);
		/** @var CSSMathProduct $result */
		$this->assertEquals(2, $result->length);
		$this->assertEquals(-1.5, $result->inner_values[0]->value);
		$this->assertEquals('px', $result->inner_values[0]->unit);
		$this->assertEquals(2, $result->inner_values[1]->value);
		$this->assertEquals('number', $result->inner_values[1]->unit);
		$this->assertEquals($input, (string) $result);
	}

	/**
	 * Test parse with mixed whitespace in complex expression.
	 */
	public function testParseComplexWithWhitespace(): void
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
	public function testParseEmptyCalc(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid expression.');
		CSSCalcParser::parse('calc()');
	}

	public function testParseWhitespaceCalc(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid expression.');
		CSSCalcParser::parse('calc( )');
	}

	public function testParseWithMultipleSpaces(): void
	{
		$input = 'calc(10px  +  5px)';
		$result = CSSCalcParser::parse($input);
		$this->assertInstanceOf(CSSMathSum::class, $result);
		$this->assertEquals('calc(10px + 5px)', (string) $result);
	}

	public function testParseUnaryOperatorsAndScientificNotation(): void
	{
		$result = CSSCalcParser::parse('calc(-(10px + 2e1px) + .5px)');

		$this->assertInstanceOf(CSSMathSum::class, $result);
		$this->assertInstanceOf(CSSMathNegate::class, $result->inner_values[0]);
		$this->assertEquals(0.5, $result->inner_values[1]->value);
		$this->assertEquals('calc(-(10px + 20px) + 0.5px)', (string) $result);
	}

	public function testRejectsTrailingGarbageAfterCalc(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		CSSCalcParser::parse('calc(10px + 2px) junk');
	}

	public function testMathInvertParenthesizesInnerExpressionInProductAndHandlesLongSumChain(): void
	{
		$invertSum = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert(
			new CSSMathSum([new CSSUnitValue(10, 'px'), new CSSUnitValue(5, 'px')]),
		);
		$product = new CSSMathProduct([new CSSUnitValue(2, 'number'), $invertSum]);
		$this->assertSame('calc(2 / (10px + 5px))', (string) $product);

		$doubleInvert = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert($invertSum);
		$this->assertSame('calc(1 / (1 / (10px + 5px)))', (string) $doubleInvert);

		$terms = [];
		for ($i = 1; $i <= 200; $i++) {
			$terms[] = $i . 'px';
		}
		$longCalc = 'calc(' . implode(' + ', $terms) . ')';
		$parsed = CSSCalcParser::parse($longCalc);
		$this->assertInstanceOf(CSSMathSum::class, $parsed);
		$this->assertCount(200, $parsed->inner_values);

		$multiPercent = CSSCalcParser::parse('calc(10px + 50% + 20%)');
		$this->assertInstanceOf(CSSMathSum::class, $multiPercent);
		$this->assertSame('length', $multiPercent->type()->percentHint);

		$clamped = \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue::parse('clamp(10px, 50%, 80%)');
		$this->assertSame('length', $clamped->type()->percentHint);
	}

	public function testRejectsMalformedOperatorsDotsSpacesAndCommentsWhileSupportingDepth256(): void
	{
		foreach ([
			'calc(10.px)',
			'calc (10px)',
			'calc(10px +/*c*/5px)',
		] as $invalid) {
			try {
				CSSCalcParser::parse($invalid);
				$this->fail('Expected invalid calc expression to throw: ' . $invalid);
			} catch (\InvalidArgumentException $e) {
				$this->assertNotSame('', $e->getMessage());
			}
		}

		$minWithComment = \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue::parse('min(10px /*c*/, 20px)');
		$this->assertSame('min(10px, 20px)', (string) $minWithComment);

		$deepCalc = 'calc(' . str_repeat('(', 254) . '10px + 5px' . str_repeat(')', 254) . ')';
		$parsedDeep = CSSCalcParser::parse($deepCalc);
		$this->assertInstanceOf(CSSMathSum::class, $parsedDeep);
		$this->assertSame('calc(10px + 5px)', (string) $parsedDeep);
	}
}
