<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM;

use Error;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\TypedOM\StylePropertyMap;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSImageValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSPerspective;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSRotate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSScale;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTransformValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTranslate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathConstant;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;
use PHPUnit\Framework\TestCase;

final class TypedOMPhase4RegressionTest extends TestCase
{
	public function testCssSupportsRejectsMaliciousOrUnbalancedSequences(): void
	{
		// Guard B3: unclosed comment must not silently validate via removeComments()
		$this->assertFalse(CSS::supports('(color: red) /* unclosed'));
		$this->assertFalse(CSS::supports('/* only open'));

		// Guard B3: trailing backslash and escaped newlines
		$this->assertFalse(CSS::supports('(color: red)\\'));
		$this->assertFalse(CSS::supports("(color: re\\\nd)"));
		$this->assertFalse(CSS::supports("(color: re\\\rd)"));
		$this->assertFalse(CSS::supports("(color: re\\\fd)"));

		// Guard B3: invalid unquoted url() and nesting limit
		$this->assertFalse(CSS::supports('(background-image: url(foo"bar))'));
		$this->assertFalse(CSS::supports('(background-image: url(unclosed'));
		$this->assertFalse(CSS::supports('(((color: red)))', null, new CSSParserOptions(maxDepth: 2)));
		$this->assertTrue(CSS::supports('((color: red))', null, new CSSParserOptions(maxDepth: 2)));

		// Unknown non-custom property rejects fast; known flat ASCII accepts
		$this->assertFalse(CSS::supports('not-a-real-property', '10px'));
		$this->assertTrue(CSS::supports('--custom-prop', '10px solid red'));
		$this->assertTrue(CSS::supports('color', 'red'));
	}

	public function testStylePropertyMapPreservesGrammarAndNestedDepthChecks(): void
	{
		$decl = new CSSStyleDeclaration();
		$map = new StylePropertyMap($decl);

		// Guard M3: CSSUnitValue fast path still enforces property grammar
		$this->expectException(InvalidArgumentException::class);
		$map->set('color', CSS::px(10));
	}

	public function testStylePropertyMapEnforcesNestedBracketDepthLimitOnStrings(): void
	{
		$decl = new CSSStyleDeclaration('', new CSSParserOptions(maxDepth: 2));
		$map = new StylePropertyMap($decl);

		$this->expectException(CSSParserLimitException::class);
		$map->set('--grid', '[[[10px]]]');
	}

	public function testTransformValueToMatrixPreserves3DIdentityFlag(): void
	{
		// Guard M4: explicit 3D identity components keep is2D === false on output DOMMatrix
		$translate3d = new CSSTransformValue([
			new CSSTranslate(CSS::px(0), CSS::px(0), CSS::px(0)),
		]);
		$this->assertFalse($translate3d->is2D);
		$this->assertFalse($translate3d->toMatrix()->is2D);

		$scale3d = new CSSTransformValue([
			new CSSScale(1, 1, 1),
		]);
		$this->assertFalse($scale3d->is2D);
		$this->assertFalse($scale3d->toMatrix()->is2D);

		$rotate3d = new CSSTransformValue([
			new CSSRotate(0, 0, 1, CSS::deg(0)),
		]);
		$this->assertFalse($rotate3d->is2D);
		$this->assertFalse($rotate3d->toMatrix()->is2D);

		$perspectiveNone = new CSSTransformValue([
			new CSSPerspective(new CSSKeywordValue('none')),
		]);
		$this->assertFalse($perspectiveNone->is2D);
		$this->assertFalse($perspectiveNone->toMatrix()->is2D);
	}

	public function testUnparsedValueAccountsForCanonicalRenderedByteExpansion(): void
	{
		// Guard M6: 'var(--a,b)' is 10 input bytes but serializes canonically as 'var(--a, b)' (11 bytes)
		$tight = CSSUnparsedValue::fromCssText('var(--a,b)', new CSSParserOptions(maxBytes: 11));
		$this->assertSame('var(--a, b)', (string) $tight);

		$this->expectException(CSSParserLimitException::class);
		CSSUnparsedValue::fromCssText('var(--a,b)', new CSSParserOptions(maxBytes: 10));
	}

	public function testUnparsedValueAccountsForReplacementCharInEscapedNull(): void
	{
		// Guard M6: 'var(--\0)' is 9 input bytes, while '\\0' decodes to U+FFFD (3 UTF-8 bytes) -> 10 rendered bytes
		$valid = CSSUnparsedValue::fromCssText('var(--\\0)', new CSSParserOptions(maxBytes: 10));
		$this->assertSame("var(--\u{FFFD})", (string) $valid);

		$this->expectException(CSSParserLimitException::class);
		CSSUnparsedValue::fromCssText('var(--\\0)', new CSSParserOptions(maxBytes: 9));
	}

	public function testIterativeMathSimplifierAndBaseMagicMethods(): void
	{
		$node = CSS::px(12);
		for ($i = 0; $i < 40; $i++) {
			$node = $i % 2 === 0 ? new CSSMathNegate($node) : new CSSMathInvert($node);
		}
		// Ensure deep alternating negate/invert simplifies cleanly without stack recursion issues
		$parsed = CSSNumericValue::parse('calc(-(-12px))');
		$this->assertSame('12px', (string) $parsed);
		$this->assertSame((string) $node, (string) $node->clone());

		$kw = new CSSKeywordValue('auto');
		$this->assertFalse(isset($kw->nonExistent));
		$this->expectException(Error::class);
		$_ = $kw->__get('nonExistent');
	}

	public function testNestedNegateInvertParityAndUnitValueMathAddSub(): void
	{
		// Double negate preserves positive sign across unit & sum simplification; triple negate flips negative
		$doubleNeg = new CSSMathNegate(new CSSMathNegate(CSS::px(5)));
		$this->assertSame('5px', (string) $doubleNeg->simplify());

		$tripleNeg = new CSSMathNegate($doubleNeg);
		$this->assertSame('-5px', (string) $tripleNeg->simplify());

		$sum = CSSNumericValue::parse('calc(2px + 3em)');
		$doubleNegSum = new CSSMathNegate(new CSSMathNegate($sum));
		$this->assertSame('calc(3em + 3px)', (string) $doubleNegSum->add(CSS::px(1))->simplify());

		// Double invert preserves non-inverted factor; triple invert keeps reciprocal
		$doubleInv = new CSSMathInvert(new CSSMathInvert(CSS::number(4)));
		$this->assertSame('4', (string) $doubleInv->simplify());

		$tripleInv = new CSSMathInvert($doubleInv);
		$this->assertSame('0.25', (string) $tripleInv->simplify());

		$productWithDoubleInvert = new CSSMathProduct([CSS::px(1), $doubleInv]);
		$this->assertSame('4px', (string) $productWithDoubleInvert->simplify());

		// Unitless CSSUnitValue add/sub with CSSMathConstant and CSSMathSum must not float-cast objects
		$this->assertSame('calc(1 + pi)', (string) CSS::number(1)->add(CSSMathConstant::pi()));
		$this->assertSame('calc(5 - (2 + e))', (string) CSS::number(5)->sub(new CSSMathSum([CSS::number(2), CSSMathConstant::e()])));
	}

	public function testImageValueRejectsInternalSemicolonsAndSerializeMathEnforcesFlattenedDepth(): void
	{
		// Semicolon inside non-url image function must be rejected by fromPreValidatedInternal and fall back via parseValidated
		$fallback = CSSStyleValue::parseValidated('', 'linear-gradient(red; blue)', CSSParserOptions::default(), 0, null, false);
		$this->assertInstanceOf(CSSUnparsedValue::class, $fallback);

		try {
			CSSImageValue::fromPreValidatedInternal('linear-gradient(red; blue)', CSSParserOptions::default(), 15, 'linear-gradient');
			self::fail('Expected InvalidArgumentException for semicolon inside linear-gradient().');
		} catch (InvalidArgumentException) {
			$this->addToAssertionCount(1);
		}

		// Product containing an invert chain: depth 510 + Product + Invert = tree depth 512 serializes within limit; +1 exceeds
		$chain = CSS::number(2);
		for ($i = 0; $i < 510; $i++) {
			$chain = new CSSMathSum([$chain, CSS::number(1)]);
		}
		$atLimit = new CSSMathProduct([CSS::px(1), new CSSMathInvert($chain)]);
		$this->assertStringStartsWith('calc(1px / (', (string) $atLimit);

		$this->expectException(CSSParserLimitException::class);
		$tooDeep = new CSSMathProduct([CSS::px(1), new CSSMathInvert(new CSSMathSum([$chain, CSS::number(1)]))]);
		$tooDeep->__toString();
	}

	public function testDomMatrix3dInvertSingularOverflowAndEscapeAndPropertyMapEdges(): void
	{
		// 3D invertible perspective matrix preserves non-2D inverse
		$m = new DOMMatrix([
			1, 0, 0, 0,
			0, 1, 0, 0,
			0, 0, 1, -0.5,
			0, 0, 0, 1,
		]);
		$this->assertFalse($m->is2D);
		$inv = $m->inverse();
		$this->assertFalse($inv->is2D);
		$prod = $m->multiply($inv);
		$this->assertEqualsWithDelta(1.0, $prod->m44, 1e-9);
		$this->assertEqualsWithDelta(0.0, $prod->m34, 1e-9);

		// Singular 3D matrix becomes all-NaN with is2D = false
		$singular = new DOMMatrix([
			1, 0, 0, 0,
			0, 1, 0, 0,
			0, 0, 0, 0,
			0, 0, 0, 1,
		]);
		$singular->invertSelf();
		$this->assertFalse($singular->is2D);
		$this->assertTrue(is_nan($singular->m11));

		// Overflow inside multiplyAffine2DInternal stays atomic on the 3D matrix elements
		$huge = new DOMMatrix([1e308, 0, 0, 1, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1]);
		$snapshot = $huge->toFloat64Array();
		try {
			$huge->scaleSelf(10);
			self::fail('Expected InvalidArgumentException on affine matrix overflow.');
		} catch (InvalidArgumentException) {
			$this->assertSame($snapshot, $huge->toFloat64Array());
		}

		// CSS::escape edge cases
		$this->assertSame('-\30 ', CSS::escape('-0'));
		$this->assertSame('-\39 ', CSS::escape('-9'));
		$this->assertSame('-_', CSS::escape('-_'));
		$this->assertSame('-é', CSS::escape('-é'));

		// StylePropertyMap empty custom prop, multi-item list via append, multi-arg shorthand set, and typed append validation
		$map = new StylePropertyMap(new CSSStyleDeclaration());
		$map->set('--empty', '');
		$this->assertSame('--empty: ;', $map->get('--empty') !== null ? '--empty: ' . trim((string) $map->get('--empty')) . ';' : '');
		$map->set('margin', CSS::px(10), CSS::px(20));
		$this->assertCount(1, $map->getAll('margin'));
		$map->set('transition-duration', CSS::s(1));
		$map->append('transition-duration', CSS::ms(200), CSS::ms(300));
		$this->assertCount(3, $map->getAll('transition-duration'));

		$this->expectException(InvalidArgumentException::class);
		$map->append('transition-duration', CSS::px(10));
	}
}
