<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM;

use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\TypedOM\StylePropertyMap;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMatrixComponent;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSPerspective;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSPositionValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSRotate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSScale;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSSkew;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSSkewX;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSSkewY;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTransformValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTranslate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathClamp;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;
use PHPUnit\Framework\TestCase;

final class TypedOMAdvancedTest extends TestCase
{
	public function testExactUnitConversionsAndCalcParserEdgeCases(): void
	{
		self::assertEqualsWithDelta(96.0, CSS::pt(72)->to('px')->value, 1e-9);
		self::assertEqualsWithDelta(96.0, CSS::cm(2.54)->to('px')->value, 1e-9);
		self::assertEqualsWithDelta(96.0, CSS::mm(25.4)->to('px')->value, 1e-9);
		self::assertEqualsWithDelta(2000.0, CSS::khz(2)->to('hz')->value, 1e-9);

		$noSpaces = CSSNumericValue::parse('calc(10px+5px)');
		self::assertInstanceOf(CSSMathSum::class, $noSpaces);
		self::assertSame('calc(10px + 5px)', (string) $noSpaces);

		$withComments = CSSNumericValue::parse('calc(/* lead */ 10px + /* mid */ 5px)');
		self::assertSame('calc(10px + 5px)', (string) $withComments);

		$mixedPercent = CSSNumericValue::parse('calc(100% - 10px)');
		$type = $mixedPercent->type();
		self::assertSame(1, $type->length);
		self::assertSame('length', $type->percentHint);
	}

	public function testMathSubclassesAndMutableUnitValues(): void
	{
		$px = new CSSUnitValue(10, 'px');
		$px->value = 25;
		self::assertSame('25px', (string) $px);

		$numberSum = CSS::number(10)->add(5);
		self::assertInstanceOf(CSSUnitValue::class, $numberSum);
		self::assertSame(15.0, $numberSum->value);

		$sum = $px->add(CSS::em(1));
		self::assertInstanceOf(CSSMathSum::class, $sum);
		self::assertSame('sum', $sum->operator);
		self::assertSame('calc(25px + 1em)', (string) $sum);

		$product = $px->mul(2);
		self::assertInstanceOf(CSSMathProduct::class, $product);
		self::assertSame('product', $product->operator);
		self::assertSame(1, $product->type()->length);

		$negate = new CSSMathNegate($px);
		self::assertSame('negate', $negate->operator);
		self::assertSame($px, $negate->value);

		$invert = new CSSMathInvert(CSS::s(2));
		self::assertSame('invert', $invert->operator);
		self::assertSame(-1, $invert->type()->time);

		$clamp = new CSSMathClamp(CSS::px(10), CSS::vw(5), CSS::px(100));
		self::assertSame('clamp', $clamp->operator);
		self::assertSame('10px', (string) $clamp->lower);
		self::assertSame('5vw', (string) $clamp->value);
		self::assertSame('100px', (string) $clamp->upper);
	}

	public function testTransformCompositionMatricesAndMutability(): void
	{
		$translate = new CSSTranslate(CSSNumericValue::parse('calc(10px + 5px)'), CSS::px(20));
		self::assertSame('translate(calc(10px + 5px), 20px)', (string) $translate);

		$rotateZ = CSSStyleValue::parse('transform', 'rotateZ(90deg)');
		self::assertInstanceOf(CSSTransformValue::class, $rotateZ);
		self::assertFalse($rotateZ->is2D);
		$rotMatrix = $rotateZ->toMatrix();
		self::assertEqualsWithDelta(0.0, $rotMatrix[0], 1e-6);
		self::assertEqualsWithDelta(1.0, $rotMatrix[1], 1e-6);
		self::assertEqualsWithDelta(-1.0, $rotMatrix[4], 1e-6);

		$composedA = new CSSTransformValue([new CSSTranslate(10, 0), new CSSScale(2, 3)]);
		$composedB = new CSSTransformValue([new CSSScale(2, 3), new CSSTranslate(10, 0)]);
		self::assertEqualsWithDelta(10.0, $composedA->toMatrix()[12], 1e-9);
		self::assertEqualsWithDelta(20.0, $composedB->toMatrix()[12], 1e-9);

		$skew = new CSSSkew(CSS::deg(45), CSS::deg(0));
		self::assertEqualsWithDelta(1.0, $skew->toMatrix()[4], 1e-6);
		self::assertEqualsWithDelta(1.0, (new CSSSkewX(CSS::deg(45)))->toMatrix()[4], 1e-6);
		self::assertEqualsWithDelta(1.0, (new CSSSkewY(CSS::deg(45)))->toMatrix()[1], 1e-6);

		$perspective = new CSSPerspective(200);
		self::assertEqualsWithDelta(-0.005, $perspective->toMatrix()[11], 1e-9);
		self::assertSame(CSSTransformValue::class, CSSTransformValue::class);
		self::assertSame(1.0, (new CSSPerspective('none'))->toMatrix()[0]);

		$matrix = new CSSMatrixComponent([1, 0, 0, 1, 15, 25]);
		$matrix->matrix = [2, 0, 0, 2, 30, 40];
		self::assertSame(30.0, $matrix->toMatrix()[12]);

		$position = new CSSPositionValue(new CSSKeywordValue('left'), CSS::px(10));
		$position->x = CSS::percent(50);
		self::assertSame('50% 10px', (string) $position);
	}

	public function testStylePropertyMapAndUnparsedBoundaries(): void
	{
		$map = new StylePropertyMap(new CSSStyleDeclaration());
		$map->set('margin', CSS::px(10), CSS::px(20));
		$map->append('background-image', 'url("a.png")', 'url("b.png")');

		self::assertSame('10px 20px', $map->getStyleDeclaration()->getPropertyValue('margin'));
		self::assertSame(['margin', 'background-image'], $map->keys());
		self::assertCount(2, $map->entries());
		self::assertCount(2, $map->values());

		$visited = [];
		$map->forEach(static function (array $values, string $property) use (&$visited): void {
			$visited[$property] = count($values);
		});
		self::assertSame(['margin' => 1, 'background-image' => 2], $visited);

		$unparsed = CSSUnparsedValue::fromCssText('myvar(--x) + var(--y, 1px)');
		self::assertCount(2, $unparsed);
		self::assertSame('myvar(--x) + ', $unparsed[0]);
		self::assertSame('--y', $unparsed[1]->variable);
		self::assertSame('1px', (string) $unparsed[1]->fallback);
	}

	public function testStandaloneCollectionsExposeWebIdlIterableHelpers(): void
	{
		$numericArray = new CSSNumericArray([CSS::px(10), CSS::percent(50)]);
		self::assertNotInstanceOf(CSSNumericValue::class, $numericArray);
		self::assertSame(2, $numericArray->length);
		self::assertSame([0, 1], $numericArray->keys());
		self::assertCount(2, $numericArray->values());
		self::assertSame(10.0, $numericArray->entries()[0][1]->value);
		$seen = [];
		$numericArray->forEach(static function (CSSNumericValue $v, int $i) use (&$seen): void {
			$seen[$i] = (string) $v;
		});
		self::assertSame([0 => '10px', 1 => '50%'], $seen);

		$unparsed = CSSUnparsedValue::fromCssText('1px solid var(--c)');
		self::assertSame([0, 1], $unparsed->keys());
		self::assertCount(2, $unparsed->values());
		self::assertSame('1px solid ', $unparsed->entries()[0][1]);
		$unparsedSeen = [];
		$unparsed->forEach(static function (mixed $v, int $i) use (&$unparsedSeen): void {
			$unparsedSeen[$i] = (string) $v;
		});
		self::assertSame([0 => '1px solid ', 1 => 'var(--c)'], $unparsedSeen);

		$transform = new CSSTransformValue([new CSSTranslate(10, 0), new CSSScale(2, 2)]);
		self::assertSame([0, 1], $transform->keys());
		self::assertCount(2, $transform->values());
		self::assertCount(2, $transform->entries());
		$transformSeen = [];
		$transform->forEach(static function (mixed $comp, int $i) use (&$transformSeen): void {
			$transformSeen[$i] = (string) $comp;
		});
		self::assertSame([0 => 'translate(10px, 0px)', 1 => 'scale(2, 2)'], $transformSeen);
	}
}
