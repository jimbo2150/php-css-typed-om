<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\CSSOM;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSColorProfileRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSCustomMediaRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFaceRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFeatureValuesRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSMediaRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSNestedDeclarations;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSOpaqueRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParser;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSSourceSpan;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use PHPUnit\Framework\TestCase;

final class CSSSourceSpanTest extends TestCase
{
	public function testSpansAreDisabledByDefaultAndEnabledOptIn(): void
	{
		$css = "a {\n\tcolor: red;\n}";

		$defaultSheet = CSSStyleSheet::parse($css);
		/** @var CSSStyleRule $defaultRule */
		$defaultRule = $defaultSheet->cssRules[0];
		self::assertNull($defaultRule->getSourceSpan());
		self::assertNull($defaultRule->sourceSpan);
		self::assertFalse(isset($defaultRule->sourceSpan));
		self::assertNull($defaultRule->style->getPropertySourceSpan('color'));
		self::assertSame([], $defaultRule->style->getSourceSpans());

		$trackedSheet = CSSStyleSheet::parse($css, new CSSParserOptions(trackSpans: true));
		/** @var CSSStyleRule $trackedRule */
		$trackedRule = $trackedSheet->cssRules[0];
		$ruleSpan = $trackedRule->sourceSpan;
		self::assertInstanceOf(CSSSourceSpan::class, $ruleSpan);
		self::assertTrue(isset($trackedRule->sourceSpan));
		self::assertSame('1:1-3:2', (string) $ruleSpan);
		self::assertSame([
			'startOffset' => 0,
			'endOffset' => strlen($css),
			'startLine' => 1,
			'startColumn' => 1,
			'endLine' => 3,
			'endColumn' => 2,
		], $ruleSpan->toArray());

		$declSpan = $trackedRule->style->getPropertySourceSpan('color');
		self::assertInstanceOf(CSSSourceSpan::class, $declSpan);
		self::assertSame('2:2-2:12', (string) $declSpan);
		self::assertSame(['color' => $declSpan], $trackedRule->style->getSourceSpans());
	}

	public function testSpansHandleNewlineFlavorsUtf8ColumnsAndLeadingBom(): void
	{
		$css = "\xEF\xBB\xBF/* héllo */\r\n.café { color: red; }\f.next { margin: 1px; }\r.end { left: 2px; }";
		$sheet = CSSStyleSheet::parse($css, new CSSParserOptions(trackSpans: true));
		self::assertCount(3, $sheet->cssRules);

		/** @var CSSStyleRule $first */
		$first = $sheet->cssRules[0];
		self::assertSame('2:1-2:22', (string) $first->sourceSpan);
		// Verify character columns count `.café` (5 code points) instead of 6 UTF-8 bytes
		$colorSpan = $first->style->getPropertySourceSpan('color');
		self::assertNotNull($colorSpan);
		self::assertSame('2:9-2:19', (string) $colorSpan);

		/** @var CSSStyleRule $second */
		$second = $sheet->cssRules[1];
		self::assertSame('3:1-3:23', (string) $second->sourceSpan);

		/** @var CSSStyleRule $third */
		$third = $sheet->cssRules[2];
		self::assertSame('4:1-4:20', (string) $third->sourceSpan);

		// BOM only on single-line stylesheet starts at 1:1
		$bomSingle = CSSStyleSheet::parse("\xEF\xBB\xBFa { color: blue; }", new CSSParserOptions(trackSpans: true));
		self::assertSame('1:1-1:19', (string) $bomSingle->cssRules[0]->sourceSpan);
	}

	public function testFastPathAndSlowPathProduceIdenticalDeclarationSpans(): void
	{
		$fastStyle = CSSParser::parseDeclarationBlock('  color: red;  margin: 10px 20px; ', new CSSParserOptions(trackSpans: true));
		$slowStyle = CSSParser::parseDeclarationBlock('  color: /*c*/ red;  margin: 10px /*c*/ 20px; ', new CSSParserOptions(trackSpans: true));

		self::assertSame('1:3-1:13', (string) $fastStyle->getPropertySourceSpan('color'));
		self::assertSame('1:3-1:19', (string) $slowStyle->getPropertySourceSpan('color'));

		$fastSlice = CSSParser::parseDeclarationBlock('color: red;', new CSSParserOptions(trackSpans: true));
		$slowSlice = CSSParser::parseDeclarationBlock('co\6c or: red;', new CSSParserOptions(trackSpans: true));
		$fastSpan = $fastSlice->getPropertySourceSpan('color');
		$slowSpan = $slowSlice->getPropertySourceSpan('color');
		self::assertNotNull($fastSpan);
		self::assertNotNull($slowSpan);
		self::assertSame(1, $fastSpan->startColumn);
		self::assertSame(1, $slowSpan->startColumn);
		self::assertSame('color: red', substr('color: red;', $fastSpan->startOffset, $fastSpan->endOffset - $fastSpan->startOffset));
	}

	public function testShorthandSpansImportantPreservationAndMutationInvalidation(): void
	{
		$css = "a {\n  margin: 1px 2px;\n  color: red !important;\n  color: blue;\n  padding: 5px;\n  padding-top: 9px;\n}";
		$sheet = CSSStyleSheet::parse($css, new CSSParserOptions(trackSpans: true));
		/** @var CSSStyleRule $rule */
		$rule = $sheet->cssRules[0];
		$style = $rule->style;

		// Shorthand returns shared span across all longhands
		$marginSpan = $style->getPropertySourceSpan('margin');
		self::assertNotNull($marginSpan);
		self::assertSame('2:3-2:18', (string) $marginSpan);
		self::assertSame($marginSpan, $style->getPropertySourceSpan('margin-top'));
		self::assertSame($marginSpan, $style->getPropertySourceSpan('margin-left'));

		// Winning !important span is NOT overwritten by ignored non-!important declaration
		$colorSpan = $style->getPropertySourceSpan('color');
		self::assertNotNull($colorSpan);
		self::assertSame('3:3-3:24', (string) $colorSpan);

		// Partial longhand override invalidates shorthand shared span while untouched longhands keep theirs
		self::assertNull($style->getPropertySourceSpan('padding'));
		self::assertSame('5:3-5:15', (string) $style->getPropertySourceSpan('padding-right'));
		self::assertSame('6:3-6:19', (string) $style->getPropertySourceSpan('padding-top'));

		// Mutating or removing clears stale spans
		$style->setProperty('margin-top', '9px');
		self::assertNull($style->getPropertySourceSpan('margin'));
		self::assertNull($style->getPropertySourceSpan('margin-top'));
		self::assertSame($marginSpan, $style->getPropertySourceSpan('margin-bottom'));

		$style->removeProperty('color');
		self::assertNull($style->getPropertySourceSpan('color'));

		$style->setCssText('display: block;');
		self::assertSame([], $style->getSourceSpans());

		$style->setCssText('display: grid;', new CSSParserOptions(trackSpans: true));
		self::assertSame('1:1-1:14', (string) $style->getPropertySourceSpan('display'));

		// Overridden trailing nested declarations preserve earlier start offset
		$nestedSheet = CSSStyleSheet::parse(
			".card {\n  &:hover { color: navy; }\n  color: red;\n  color: blue;\n}",
			new CSSParserOptions(trackSpans: true),
		);
		/** @var CSSStyleRule $cardRule */
		$cardRule = $nestedSheet->cssRules[0];
		/** @var CSSNestedDeclarations $trailingDecls */
		$trailingDecls = $cardRule->cssRules[1];
		self::assertInstanceOf(CSSNestedDeclarations::class, $trailingDecls);
		self::assertSame('3:3-4:14', (string) $trailingDecls->sourceSpan);

		// parseWithOffsets shifts tracked spans by baseOffset
		$diags = [];
		$shiftedStyle = \Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration::parseWithOffsets(
			'color: red;',
			true,
			$diags,
			100,
			new CSSParserOptions(trackSpans: true),
		);
		$shiftedSpan = $shiftedStyle->getPropertySourceSpan('color');
		self::assertNotNull($shiftedSpan);
		self::assertSame(100, $shiftedSpan->startOffset);
		self::assertSame(110, $shiftedSpan->endOffset);
	}

	public function testSpansAcrossNestedAtRulesImplicitNestedDeclarationsAndInsertRule(): void
	{
		$css = <<<'CSS'
@custom-media --sm (width < 40rem);
@color-profile --srgb { rendering-intent: perceptual; }
@font-face { font-family: Test; src: url("t.woff2"); }
@font-feature-values Test { @styleset { alt: 1; } }
@media screen {
  .card {
    color: navy;
    &:hover { color: teal; }
    margin: 4px;
  }
}
@unknown foo;
.eof { color: pink
CSS;

		$sheet = CSSStyleSheet::parse($css, new CSSParserOptions(trackSpans: true));
		self::assertCount(7, $sheet->cssRules);
		self::assertInstanceOf(CSSCustomMediaRule::class, $sheet->cssRules[0]);
		self::assertSame('1:1-1:36', (string) $sheet->cssRules[0]->sourceSpan);

		self::assertInstanceOf(CSSColorProfileRule::class, $sheet->cssRules[1]);
		self::assertSame('2:1-2:56', (string) $sheet->cssRules[1]->sourceSpan);

		self::assertInstanceOf(CSSFontFaceRule::class, $sheet->cssRules[2]);
		self::assertSame('3:1-3:55', (string) $sheet->cssRules[2]->sourceSpan);

		self::assertInstanceOf(CSSFontFeatureValuesRule::class, $sheet->cssRules[3]);
		self::assertSame('4:1-4:52', (string) $sheet->cssRules[3]->sourceSpan);

		/** @var CSSMediaRule $media */
		$media = $sheet->cssRules[4];
		self::assertSame('5:1-11:2', (string) $media->sourceSpan);

		/** @var CSSStyleRule $card */
		$card = $media->cssRules[0];
		self::assertSame('6:3-10:4', (string) $card->sourceSpan);
		self::assertSame('7:5-7:16', (string) $card->style->getPropertySourceSpan('color'));

		self::assertInstanceOf(CSSStyleRule::class, $card->cssRules[0]);
		self::assertSame('8:5-8:29', (string) $card->cssRules[0]->sourceSpan);

		self::assertInstanceOf(CSSNestedDeclarations::class, $card->cssRules[1]);
		$nestedDecls = $card->cssRules[1];
		self::assertSame('9:5-9:16', (string) $nestedDecls->sourceSpan);
		self::assertSame('9:5-9:16', (string) $nestedDecls->style->getPropertySourceSpan('margin'));

		self::assertInstanceOf(CSSOpaqueRule::class, $sheet->cssRules[5]);
		self::assertSame('12:1-12:14', (string) $sheet->cssRules[5]->sourceSpan);

		// Unclosed EOF block captures span up to EOF
		self::assertInstanceOf(CSSStyleRule::class, $sheet->cssRules[6]);
		self::assertSame('13:1-13:19', (string) $sheet->cssRules[6]->sourceSpan);
		self::assertSame('13:8-13:19', (string) $sheet->cssRules[6]->style->getPropertySourceSpan('color'));

		// insertRule inherits sheet trackSpans option
		$idx = $sheet->insertRule('.added { gap: 8px; }', 6);
		/** @var CSSStyleRule $inserted */
		$inserted = $sheet->cssRules[$idx];
		self::assertSame('1:1-1:21', (string) $inserted->sourceSpan);
		self::assertSame('1:10-1:18', (string) $inserted->style->getPropertySourceSpan('gap'));
	}
}
