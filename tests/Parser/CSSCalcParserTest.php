<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Parser;

use Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;
use PHPUnit\Framework\TestCase;

class CSSCalcParserTest extends TestCase
{
	public function testParseSimpleAddition(): void
	{
		$result = CSSCalcParser::parse('calc(10px + 5px)');

		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals(15, $result->value);
		$this->assertEquals('px', $result->unit);
		$this->assertEquals('15px', (string) $result);
	}

	public function testParseSimpleSubtraction(): void
	{
		$result = CSSCalcParser::parse('calc(10px - 5px)');

		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals(5, $result->value);
		$this->assertEquals('px', $result->unit);
		$this->assertEquals('5px', (string) $result);
	}

	public function testParseSimpleMultiplication(): void
	{
		$result = CSSCalcParser::parse('calc(10px * 2)');

		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals(20, $result->value);
		$this->assertEquals('px', $result->unit);
		$this->assertEquals('20px', (string) $result);
	}

	public function testParseSimpleDivision(): void
	{
		$result = CSSCalcParser::parse('calc(10px / 2)');

		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals(5, $result->value);
		$this->assertEquals('px', $result->unit);
		$this->assertEquals('5px', (string) $result);
	}

	public function testParseMixedOperations(): void
	{
		$result = CSSCalcParser::parse('calc(10px + 5px * 2)');

		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals(20, $result->value);
		$this->assertEquals('px', $result->unit);
		$this->assertEquals('20px', (string) $result);
	}

	public function testParseWithParentheses(): void
	{
		$result = CSSCalcParser::parse('calc((10px + 5px) * 2)');

		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals(30, $result->value);
		$this->assertEquals('px', $result->unit);
		$this->assertEquals('30px', (string) $result);
	}

	public function testParseDifferentUnits(): void
	{
		$result = CSSCalcParser::parse('calc(10px + 5em)');

		$this->assertInstanceOf(CSSMathSum::class, $result);
		/** @var CSSMathSum $result */
		$this->assertEquals(2, $result->length);
		$this->assertEquals('em', $result->inner_values[0]->unit);
		$this->assertEquals('px', $result->inner_values[1]->unit);
		$this->assertEquals('calc(5em + 10px)', (string) $result);
	}

	public function testParseNegativeNumbers(): void
	{
		$result = CSSCalcParser::parse('calc(-10px + 5px)');

		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals(-5, $result->value);
		$this->assertEquals('px', $result->unit);
		$this->assertEquals('-5px', (string) $result);
	}

	public function testParseDecimalNumbers(): void
	{
		$result = CSSCalcParser::parse('calc(1.5px * 2.5)');

		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals(3.75, $result->value);
		$this->assertEquals('px', $result->unit);
		$this->assertEquals('3.75px', (string) $result);
	}

	public function testParseComplex(): void
	{
		$result = CSSCalcParser::parse('calc(1.5 * 2.5dvw + (2px - 5%))');

		$this->assertInstanceOf(CSSMathSum::class, $result);
		/** @var CSSMathSum $result */
		$this->assertEquals(3, $result->length);

		$this->assertInstanceOf(CSSUnitValue::class, $result->inner_values[0]);
		$this->assertEquals(-5, $result->inner_values[0]->value);
		$this->assertEquals('percent', $result->inner_values[0]->unit);

		$this->assertInstanceOf(CSSUnitValue::class, $result->inner_values[1]);
		$this->assertEquals(3.75, $result->inner_values[1]->value);
		$this->assertEquals('dvw', $result->inner_values[1]->unit);

		$this->assertInstanceOf(CSSUnitValue::class, $result->inner_values[2]);
		$this->assertEquals(2, $result->inner_values[2]->value);
		$this->assertEquals('px', $result->inner_values[2]->unit);

		$this->assertEquals('calc(-5% + 3.75dvw + 2px)', (string) $result);
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
		$result = CSSCalcParser::parse('calc((10px + 5em) * 2 - 3px)');

		$this->assertInstanceOf(CSSMathSum::class, $result);
		/** @var CSSMathSum $result */
		$this->assertEquals(2, $result->length);
		$this->assertEquals(10, $result->inner_values[0]->value);
		$this->assertEquals('em', $result->inner_values[0]->unit);
		$this->assertEquals(17, $result->inner_values[1]->value);
		$this->assertEquals('px', $result->inner_values[1]->unit);
		$this->assertEquals('calc(10em + 17px)', (string) $result);
	}

	/**
	 * Test parse with extra whitespace around operators.
	 */
	public function testParseWithExtraWhitespace(): void
	{
		$result = CSSCalcParser::parse('calc( 10px  +  5px )');

		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals(15, $result->value);
		$this->assertEquals('px', $result->unit);
		$this->assertEquals('15px', (string) $result);
	}

	/**
	 * Test parse with negative decimal numbers.
	 */
	public function testParseNegativeDecimal(): void
	{
		$result = CSSCalcParser::parse('calc(-1.5px * 2)');

		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals(-3, $result->value);
		$this->assertEquals('px', $result->unit);
		$this->assertEquals('-3px', (string) $result);
	}

	/**
	 * Test parse with mixed whitespace in complex expression.
	 */
	public function testParseComplexWithWhitespace(): void
	{
		$result = CSSCalcParser::parse('calc( ( 10px + 5em ) * 2 - 3px )');

		$this->assertInstanceOf(CSSMathSum::class, $result);
		/** @var CSSMathSum $result */
		$this->assertEquals(2, $result->length);
		$this->assertEquals('calc(10em + 17px)', (string) $result);
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
		$result = CSSCalcParser::parse('calc(10px  +  5px)');
		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals('15px', (string) $result);
	}

	public function testParseUnaryOperatorsAndScientificNotation(): void
	{
		$result = CSSCalcParser::parse('calc(-(10px + 2e1px) + .5px)');

		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals(-29.5, $result->value);
		$this->assertEquals('-29.5px', (string) $result);
	}

	public function testRejectsTrailingGarbageAfterCalc(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		CSSCalcParser::parse('calc(10px + 2px) junk');
	}

	public function testMathInvertParenthesizesInnerExpressionInProductAndHandlesLongSumChain(): void
	{
		// Irreducible parser-produced CSSMathProduct and CSSMathInvert
		$irreducibleProduct = CSSCalcParser::parse('calc(2px * 5em)');
		$this->assertInstanceOf(CSSMathProduct::class, $irreducibleProduct);
		$this->assertSame('calc(10 * 1em * 1px)', (string) $irreducibleProduct);

		$irreducibleInvert = CSSCalcParser::parse('calc(1 / (1em + 2px))');
		$this->assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert::class, $irreducibleInvert);
		$this->assertSame('calc(1 / (1em + 2px))', (string) $irreducibleInvert);

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
		$this->assertInstanceOf(CSSUnitValue::class, $parsed);
		$this->assertSame(20100.0, $parsed->value);

		// Uncollapsed 200-term CSSMathSum
		$uncollapsedTerms = [];
		for ($i = 1; $i <= 200; $i++) {
			$uncollapsedTerms[] = 'abs(' . $i . 'em - ' . $i . 'px)';
		}
		$longUncollapsed = CSSCalcParser::parse('calc(' . implode(' + ', $uncollapsedTerms) . ')');
		$this->assertInstanceOf(CSSMathSum::class, $longUncollapsed);
		$this->assertSame(200, $longUncollapsed->length);

		$multiPercent = CSSCalcParser::parse('calc(10px + 50% + 20%)');
		$this->assertInstanceOf(CSSMathSum::class, $multiPercent);
		$this->assertSame('length', $multiPercent->type()->percentHint);
		$this->assertSame('calc(70% + 10px)', (string) $multiPercent);

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
		$this->assertSame('10px', (string) $minWithComment);

		$deepCalc = 'calc(' . str_repeat('(', 254) . '10px + 5px' . str_repeat(')', 254) . ')';
		$parsedDeep = CSSCalcParser::parse($deepCalc);
		$this->assertInstanceOf(CSSUnitValue::class, $parsedDeep);
		$this->assertSame('15px', (string) $parsedDeep);
	}
}
