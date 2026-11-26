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

		try {
			CSSNumericValue::parse('calc(10px+5px)');
			self::fail('Expected InvalidArgumentException when binary + lacks surrounding whitespace.');
		} catch (\InvalidArgumentException $e) {
			self::assertStringContainsString('whitespace', strtolower($e->getMessage()));
		}

		$withComments = CSSNumericValue::parse('calc(/* lead */ 10px + /* mid */ 5px)');
		self::assertSame('15px', (string) $withComments);

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
		self::assertInstanceOf(CSSUnitValue::class, $product);
		self::assertSame(50.0, $product->value);
		self::assertSame('px', $product->unit);
		self::assertSame(1, $product->type()->length);

		$dimProduct = $px->mul(CSS::em(2));
		self::assertInstanceOf(CSSMathProduct::class, $dimProduct);
		self::assertSame('product', $dimProduct->operator);
		self::assertSame(2, $dimProduct->type()->length);

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
		self::assertSame('translate(15px, 20px)', (string) $translate);

		$irrTranslate = new CSSTranslate(CSSNumericValue::parse('calc(10px + 5em)'), CSS::px(20));
		self::assertSame('translate(calc(5em + 10px), 20px)', (string) $irrTranslate);
		try {
			$irrTranslate->toMatrix();
			self::fail('Expected unresolved relative math expression in CSSTranslate::toMatrix() to throw.');
		} catch (\InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		foreach ([
			new CSSTranslate(CSS::em(5), CSS::px(10)),
			new CSSPerspective(CSS::vw(20)),
		] as $relTransform) {
			try {
				$relTransform->toMatrix();
				self::fail('Expected relative length unit in toMatrix() to throw.');
			} catch (\InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

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
		self::assertSame(2, $numericArray->length);
		self::assertSame([0, 1], $numericArray->keys());
		self::assertCount(2, $numericArray->values());
		$firstNumeric = $numericArray->entries()[0][1];
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue::class, $firstNumeric);
		self::assertSame(10.0, $firstNumeric->value);
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

	public function testTransformIndependenceCloningMatrixSyncAndPerspectiveNone(): void
	{
		$scale = new CSSScale(2);
		self::assertNotSame($scale->x, $scale->y);
		$scaleClone = $scale->clone();
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue::class, $scaleClone->x);
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue::class, $scaleClone->y);
		$scaleClone->x->value = 5;
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue::class, $scale->x);
		self::assertSame(2.0, $scale->x->value);
		self::assertSame(2.0, $scaleClone->y->value);

		$rotateX = new CSSRotate('rotateX', ['45deg']);
		$rotateClone = $rotateX->clone();
		self::assertFalse($rotateClone->is2D);
		self::assertSame('rotatex(45deg)', (string) $rotateClone);

		$translate = new CSSTranslate(CSS::px(10), CSS::px(20));
		$translate->z = CSS::px(30);
		self::assertFalse($translate->is2D);
		self::assertSame('translate3d(10px, 20px, 30px)', (string) $translate);

		$matrixComp = new CSSMatrixComponent([1, 0, 0, 1, 0, 0]);
		$matrixComp->matrix->e = 42.0;
		self::assertSame('42', $matrixComp->getValues()[4]);

		$parsed = CSSTransformValue::fromCssText('perspective(none)');
		self::assertInstanceOf(CSSPerspective::class, $parsed[0]);
		self::assertSame('perspective(none)', (string) $parsed);

		$tx = CSSTransformValue::fromCssText('translateX(10px)')[0];
		self::assertInstanceOf(CSSTranslate::class, $tx);
		$tx->y = CSS::px(25);
		self::assertSame('translate(10px, 25px)', (string) $tx);

		$sx = CSSTransformValue::fromCssText('scaleX(2)')[0];
		self::assertInstanceOf(CSSScale::class, $sx);
		$sx->y = 3;
		self::assertSame('scale(2, 3)', (string) $sx);

		$rot = new CSSRotate(CSS::deg(30));
		$rot->x = 1;
		self::assertFalse($rot->is2D);
		self::assertSame('rotate3d(1, 0, 1, 30deg)', (string) $rot);

		$unparsed = new CSSUnparsedValue('url("a)b") var(--x, 1px)');
		self::assertCount(2, $unparsed);
		$unparsedClone = clone $unparsed;
		self::assertNotSame($unparsed[1], $unparsedClone[1]);
	}

	public function testW3cTransformPositionImageAndCloneCompliance(): void
	{
		// Three-argument translate/scale always set is2D = false even with zero z / unit scaleZ
		$t3 = new CSSTranslate(CSS::px(10), CSS::px(20), CSS::px(0));
		self::assertFalse($t3->is2D);
		$s3 = new CSSScale(2, 3, 1);
		self::assertFalse($s3->is2D);

		// Negative canonical axis on CSSRotate promotes z-axis rotation to 3D and rejects zero axis
		$negZRot = new CSSRotate(0, 0, -1, CSS::deg(45));
		self::assertFalse($negZRot->is2D);
		self::assertSame('rotate3d(0, 0, -1, 45deg)', (string) $negZRot);

		try {
			new CSSRotate(0, 0, 0, CSS::deg(45));
			self::fail('Expected zero rotation axis to throw.');
		} catch (\InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// Two-keyword CSSPositionValue('center left') resolves x=left, y=center
		$pos = CSSStyleValue::parse('object-position', 'center left');
		self::assertInstanceOf(CSSPositionValue::class, $pos);
		self::assertSame('left', (string) $pos->x);
		self::assertSame('center', (string) $pos->y);

		// Non-url() images with var() fall back to CSSUnparsedValue
		$gradVar = CSSStyleValue::parse('background-image', 'linear-gradient(var(--a), red)');
		self::assertInstanceOf(CSSUnparsedValue::class, $gradVar);

		// Transform component setters reject compatible dimensions with incompatible percentHint
		$tx = new CSSTranslate(CSS::px(1), CSS::px(2));
		foreach ([
			new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum([
				CSS::s(1),
				CSS::percent(10),
			]),
			new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct([
				CSS::px(1),
				new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum([CSS::s(1), CSS::percent(10)]),
				new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert([CSS::s(1)]),
			]),
		] as $badPercentHint) {
			try {
				$tx->x = $badPercentHint;
				self::fail('Expected incompatible percentHint math value to be rejected on CSSTranslate::$x.');
			} catch (\InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		// CSSTranslate & CSSScale preserve 1-arg serialization on __clone()
		$singleTranslateClone = clone new CSSTranslate('translate', [CSS::px(10)]);
		self::assertSame('translate(10px)', (string) $singleTranslateClone);
		/** @phpstan-ignore argument.type */
		$singleScaleClone = clone new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSScale('scale', [2]);
		self::assertSame('scale(2)', (string) $singleScaleClone);

		// CSSPositionValue accepts unitless 0 (normalized to 0px) and rejects control characters
		$zeroPos = CSSPositionValue::fromCssText('0 50%');
		self::assertSame('0px 50%', (string) $zeroPos);
		try {
			CSSPositionValue::fromCssText("0\x00 0");
			self::fail('Expected control character in CSSPositionValue to throw.');
		} catch (\InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// CSSImageValue rejects declaration/block delimiters inside non-url() functions, allows ';' in url()
		$dataUrl = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSImageValue('url("data:text/css;utf8,a")');
		self::assertSame('url("data:text/css;utf8,a")', (string) $dataUrl);
		try {
			new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSImageValue('linear-gradient(red; blue)');
			self::fail('Expected ";" inside linear-gradient() to throw.');
		} catch (\InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// CSSTransformValue comments & clone deep-copy
		$trList = CSSTransformValue::fromCssText('/* lead */ translateX(10px) /* mid */ scale(2)');
		self::assertCount(2, $trList);
		$trClone = clone $trList;
		self::assertNotSame($trList[0], $trClone[0]);
	}

	public function testCssValues4MathCrossComponentIntegration(): void
	{
		// CSSStyleValue::parse() & parseAll() with comma-bearing CSS Values 4 math functions & transforms
		$roundWidth = CSSStyleValue::parse('width', 'round(up, 1em, 10px)');
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathFunction::class, $roundWidth);
		self::assertSame('round(up, 1em, 10px)', (string) $roundWidth);

		$sinOpacity = CSSStyleValue::parse('opacity', 'sin(0.5)');
		self::assertInstanceOf(CSSUnitValue::class, $sinOpacity);
		self::assertEqualsWithDelta(sin(0.5), $sinOpacity->value, 1e-12);

		$commaAll = CSSStyleValue::parseAll('background-image', 'linear-gradient(calc(sin(1deg) * 45deg), red, blue), linear-gradient(45deg, green calc(abs(-5px)), yellow)');
		self::assertCount(2, $commaAll);
		self::assertSame('linear-gradient(calc(sin(1deg) * 45deg), red, blue)', (string) $commaAll[0]);
		self::assertSame('linear-gradient(45deg, green calc(abs(-5px)), yellow)', (string) $commaAll[1]);

		$transform = CSSStyleValue::parse('transform', 'translate(round(up, 1em, 10px), abs(-2vw))');
		self::assertInstanceOf(CSSTransformValue::class, $transform);
		self::assertSame('translate(round(up, 1em, 10px), 2vw)', (string) $transform);

		$transformIrr = CSSStyleValue::parse('transform', 'translate(round(up, 1em, 10px), abs(1px - 2vw))');
		self::assertInstanceOf(CSSTransformValue::class, $transformIrr);
		self::assertSame('translate(round(up, 1em, 10px), abs(1px - 2vw))', (string) $transformIrr);

		$singleArgTranslate = CSSStyleValue::parse('transform', 'translate(round(up, 1em, 10px))');
		self::assertInstanceOf(CSSTransformValue::class, $singleArgTranslate);
		self::assertSame('translate(round(up, 1em, 10px))', (string) $singleArgTranslate);

		$posWithRound = CSSStyleValue::parse('object-position', 'left round(up, 1em, 10px)');
		self::assertInstanceOf(CSSPositionValue::class, $posWithRound);
		self::assertSame('left round(up, 1em, 10px)', (string) $posWithRound);

		// Invalid arity / incompatible unit types / bare constants on modeled properties reject with InvalidArgumentException,
		// while unmodeled properties fall back to CSSUnparsedValue
		foreach ([
			['width', 'sin()'],
			['width', 'atan2(1px, 2s)'],
			['width', 'pi'],
			['opacity', 'e'],
			['margin', 'sin()'],
		] as [$prop, $raw]) {
			try {
				CSSStyleValue::parse($prop, $raw);
				self::fail("Expected {$prop}: {$raw} to throw InvalidArgumentException.");
			} catch (\InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}
		self::assertInstanceOf(CSSUnparsedValue::class, CSSStyleValue::parse('--custom', 'sin()'));

		// StylePropertyMap set/get round-trip with CSSMathFunction, CSSMathConstant & negated constant
		$map = new StylePropertyMap(new CSSStyleDeclaration());
		$negInfExpr = new CSSMathNegate(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathConstant::negInfinity());
		$map->set('width', CSSNumericValue::parse('round(up, 1em, 10px)'));
		$map->set('opacity', \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathConstant::infinity());
		$map->set('z-index', $negInfExpr);
		$map->set('margin', CSSNumericValue::parse('abs(-2em)'), CSSNumericValue::parse('hypot(3em, 4vw)'));

		self::assertSame('round(up, 1em, 10px)', (string) $map->get('width'));
		self::assertSame('calc(infinity)', (string) $map->get('opacity'));
		self::assertSame('calc(infinity)', (string) $map->get('z-index'));
		self::assertSame('2em hypot(3em, 4vw)', $map->getStyleDeclaration()->getPropertyValue('margin'));

		$absNode = CSSNumericValue::parse('abs(-2em)');
		self::assertInstanceOf(CSSUnitValue::class, $absNode);
		self::assertSame('2em', (string) $absNode);
		$absExprNode = CSSNumericValue::parse('abs(1px - 2vw)');
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathFunction::class, $absExprNode);
		self::assertSame('abs(1px - 2vw)', $absExprNode->toBoundedCssTextInternal(null));
		self::assertSame('calc(-(-infinity))', $negInfExpr->toBoundedCssTextInternal(null));

		// CSSImageValue with CSS Values 4 math functions & escapes + unified maxMathDepth boundary
		$gradMath = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSImageValue('linear-gradient(calc(s\\69n(1deg) * 10px), red, blue)');
		self::assertSame('linear-gradient(calc(s\\69n(1deg) * 10px), red, blue)', (string) $gradMath);

		$gradDepth1 = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSImageValue(
			'linear-gradient(calc(sin(1deg)), red, blue)',
			new \Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions(maxMathDepth: 1),
		);
		self::assertSame('linear-gradient(calc(sin(1deg)), red, blue)', (string) $gradDepth1);

		foreach ([
			['linear-gradient(calc(sin(cos(1deg))), red, blue)', 1],
			['linear-gradient(calc(sin(cos(tan(1deg)))), red, blue)', 2],
		] as [$imgCss, $limit]) {
			try {
				new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSImageValue(
					$imgCss,
					new \Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions(maxMathDepth: $limit),
				);
				self::fail('Expected CSSImageValue to enforce maxMathDepth=' . $limit . ' for: ' . $imgCss);
			} catch (\Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}
	}
}
