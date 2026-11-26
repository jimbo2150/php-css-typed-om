<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM;

use Error;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\TypedOM\StylePropertyMap;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSPerspective;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSRotate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSScale;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTransformValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTranslate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate;
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
}
