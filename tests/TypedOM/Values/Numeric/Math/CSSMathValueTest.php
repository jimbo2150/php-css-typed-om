<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMax;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMin;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;
use PHPUnit\Framework\TestCase;

class CSSMathValueTest extends TestCase
{
	public function testCSSMathInvertToString(): void
	{
		$mathInvert = new CSSMathInvert(new CSSUnitValue(10, 'px'));
		self::assertSame('calc(1 / 10px)', (string)$mathInvert);
	}

	public function testCSSMathNegateToString(): void
	{
		$mathNegate = new CSSMathNegate(new CSSUnitValue(10, 'px'));
		self::assertSame('calc(-10px)', (string)$mathNegate);
	}

	public function testCSSMathSumToString(): void
	{
		$mathSum = new CSSMathSum([new CSSUnitValue(10, 'px'), new CSSUnitValue(20, 'px')]);
		self::assertSame('calc(10px + 20px)', (string)$mathSum);
	}

	public function testCSSMathSumWithSingleValueToString(): void
	{
		$mathSum = new CSSMathSum([new CSSUnitValue(10, 'px')]);
		self::assertSame('calc(10px)', (string)$mathSum);
	}

	public function testCSSMathProductToString(): void
	{
		$mathProduct = new CSSMathProduct([new CSSUnitValue(10, 'px'), new CSSUnitValue(20, 'px')]);
		self::assertSame('calc(10px * 20px)', (string)$mathProduct);
	}

	public function testCSSMathMinToString(): void
	{
		$mathMin = new CSSMathMin([new CSSUnitValue(10, 'px'), new CSSUnitValue(20, 'px')]);
		self::assertSame('min(10px, 20px)', (string)$mathMin);
	}

	public function testCSSMathMaxToString(): void
	{
		$mathMax = new CSSMathMax([new CSSUnitValue(10, 'px'), new CSSUnitValue(20, 'px')]);
		self::assertSame('max(10px, 20px)', (string)$mathMax);
	}

	public function testClampSerialization(): void
	{
		$clamp = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathClamp(
			new CSSUnitValue(10, 'px'),
			new CSSUnitValue(5, 'vw'),
			new CSSUnitValue(50, 'px'),
		);
		self::assertSame('clamp(10px, 5vw, 50px)', (string) $clamp);
	}

	public function testClone(): void
	{
		$mathSum = new CSSMathSum([new CSSUnitValue(10, 'px'), new CSSUnitValue(20, 'px')]);
		$clone = $mathSum->clone();
		$this->assertNotSame($mathSum, $clone);
		$this->assertNotSame($mathSum->values, $clone->values);
		$this->assertNotSame($mathSum->values[0], $clone->values[0]);
	}
}
