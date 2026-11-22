<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSOKLCH;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSPositionValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSImageValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTransformValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTranslate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSRotate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSScale;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSVariableReferenceValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathClamp;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CSSStyleValueParsingTest extends TestCase
{
	public function testParsesNumericKeywordsAndUnknownValues(): void
	{
		self::assertInstanceOf(CSSUnitValue::class, CSSStyleValue::parse('width', '12px'));
		self::assertInstanceOf(CSSUnitValue::class, CSSNumericValue::parse('width', '12px'));
		self::assertInstanceOf(CSSKeywordValue::class, CSSStyleValue::parse('display', 'block'));
		self::assertInstanceOf(CSSOKLCH::class, CSSStyleValue::parse('color', 'oklch(50% 0.2 30)'));
		self::assertInstanceOf(CSSUnparsedValue::class, CSSStyleValue::parse('color', 'color-mix(in srgb, red, blue)'));
		self::assertInstanceOf(CSSUnparsedValue::class, CSSStyleValue::parse('--custom', '12px'));
		self::assertInstanceOf(CSSKeywordValue::class, CSSStyleValue::parse('background-image', 'inherit'));
		self::assertInstanceOf(CSSKeywordValue::class, CSSStyleValue::parse('background-image', 'none'));
		self::assertInstanceOf(CSSImageValue::class, CSSStyleValue::parse('background-image', 'url("a.png")'));
		self::assertInstanceOf(CSSKeywordValue::class, CSSStyleValue::parse('width', 'auto'));
		self::assertInstanceOf(CSSUnparsedValue::class, CSSStyleValue::parse('width', 'banana'));
	}

	public function testParseAllSplitsOnlyTopLevelCommas(): void
	{
		$values = CSSStyleValue::parseAll('background-image', 'url("a,b.png"), linear-gradient(red, blue)');

		self::assertCount(2, $values);
		self::assertSame('url("a,b.png")', (string) $values[0]);
		self::assertSame('linear-gradient(red, blue)', (string) $values[1]);
	}

	public function testParsesTransformsAndVariableReferences(): void
	{
		$transform = CSSStyleValue::parse('transform', 'translateX(10px) rotate(45deg)');
		self::assertInstanceOf(CSSTransformValue::class, $transform);
		self::assertCount(2, $transform);
		self::assertSame('translatex(10px) rotate(45deg)', (string) $transform);

		$unparsed = CSSStyleValue::parse('--custom', 'var(--base, 2px) solid');
		self::assertInstanceOf(CSSUnparsedValue::class, $unparsed);
		self::assertInstanceOf(CSSVariableReferenceValue::class, $unparsed->getValues()[0]);
		self::assertSame('var(--base, 2px) solid', (string) $unparsed);
		self::assertSame('clamp(10px, 5vw, 100px)', (string) CSSStyleValue::parse('width', 'clamp(10px, 5vw, 100px)'));
	}

	public function testParsesClampAsTypedNumericMath(): void
	{
		$value = CSSStyleValue::parse('width', 'clamp(10px, 5vw, 100px)');

		self::assertInstanceOf(CSSMathClamp::class, $value);
		self::assertCount(3, $value->values);
	}

	public function testParsesPositionValues(): void
	{
		$position = CSSStyleValue::parse('object-position', 'left top');

		self::assertInstanceOf(CSSPositionValue::class, $position);
		self::assertSame('left top', (string) $position);
		self::assertInstanceOf(CSSPositionValue::class, CSSStyleValue::parse('object-position', 'calc(50% - 10px) center'));
	}

	public function testRejectsUnbalancedPropertyValue(): void
	{
		$this->expectException(InvalidArgumentException::class);
		CSSStyleValue::parse('color', 'rgb(10, 20, 30');
	}

	public function testKeywordValueRejectsNonIdentifiers(): void
	{
		$this->expectException(InvalidArgumentException::class);
		new CSSKeywordValue('two words');
	}

	public function testTransformComponentsExposeTypedValuesAndMatrix(): void
	{
		$transform = CSSStyleValue::parse('transform', 'translate3d(10px, 20px, 0px) rotate(90deg) scale(2, 3)');
		self::assertInstanceOf(CSSTransformValue::class, $transform);
		self::assertSame(3, $transform->length);
		self::assertFalse($transform->is2D);
		self::assertInstanceOf(CSSTranslate::class, $transform[0]);
		self::assertSame(10.0, $transform[0]->x->value);
		self::assertInstanceOf(CSSRotate::class, $transform[1]);
		self::assertSame(90.0, $transform[1]->angle->value);
		self::assertInstanceOf(CSSScale::class, $transform[2]);
		self::assertSame(3.0, $transform[2]->y->value);
		self::assertSame(10.0, $transform->toMatrix()[12]);
		self::assertCount(16, $transform->toMatrix());
	}

	public function testTransformComponentsCanBeConstructedAndUpdated(): void
	{
		$translate = new CSSTranslate(new CSSUnitValue(10, 'px'), new CSSUnitValue(20, 'px'));
		$transform = new CSSTransformValue([$translate]);
		$transform[0] = new CSSScale(2, 3);

		self::assertSame('scale(2, 3)', (string) $transform[0]);
		self::assertSame(2.0, $transform->toMatrix()[0]);
		self::assertInstanceOf(CSSTransformValue::class, $transform->clone());
	}

	public function testTypedArraysAreIterableAndRespectMutability(): void
	{
		$numericArray = new CSSNumericArray([new CSSUnitValue(1, 'px'), new CSSUnitValue(2, 'px')]);
		self::assertCount(2, $numericArray);
		self::assertSame(2.0, $numericArray[1]->value);
		self::assertCount(2, iterator_to_array($numericArray));

		$unparsed = CSSUnparsedValue::fromCssText('var(--x) solid');
		self::assertSame('--x', $unparsed[0]->variable);
		$unparsed[1] = ' dashed';
		self::assertSame('var(--x) dashed', (string) $unparsed);

		$this->expectException(InvalidArgumentException::class);
		$numericArray[] = new CSSUnitValue(3, 'px');
	}
}
