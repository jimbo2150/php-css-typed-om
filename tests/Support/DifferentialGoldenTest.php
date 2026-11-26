<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Support;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSCharsetRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSContainerRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSCounterStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFaceRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFeatureValuesRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontPaletteValuesRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFunctionDeclarations;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFunctionRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSImportRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSKeyframesRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSLayerBlockRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSLayerStatementRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSMediaRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSNamespaceRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSNestedDeclarations;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSOpaqueRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPageRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPositionTryRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPropertyRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderer;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderMode;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSScopeRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStartingStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSSupportsRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSViewTransitionRule;
use PHPUnit\Framework\TestCase;

/**
 * Golden differential contract for well-formed CSS stylesheets across refactoring phases.
 */
final class DifferentialGoldenTest extends TestCase
{
	private const string REPRESENTATIVE_CSS = <<<'CSS'
@charset "UTF-8";
@layer base, components;
@import url("tokens.css") layer(base) supports(display: grid) screen and (min-width: 480px);
@namespace svg url("http://www.w3.org/2000/svg");

@property --brand-hue {
  syntax: "<number>";
  inherits: true;
  initial-value: 210;
}

@font-face {
  font-family: "Inter";
  src: url("inter.woff2") format("woff2");
  font-display: swap;
}

@font-feature-values "Fira Code" {
  @styleset { alt-a: 1 2; }
}

@font-palette-values --warm {
  font-family: "Bungee";
  base-palette: 1;
}

@counter-style thumbs {
  system: cyclic;
  symbols: "A";
}

@position-try --custom-left {
  position-area: left;
  width: 20%;
}

@view-transition {
  navigation: auto;
}

@function --tint(--color <color>, --pct <percentage>: 20%) returns <color> {
  --mix: 1;
  result: color-mix(in oklch, var(--color), white var(--pct));
}

@keyframes pulse {
  from { opacity: 0; }
  50%, 100% { opacity: 1; }
}

@page :first {
  size: A4;
  @top-left-corner { content: "Header"; }
}

@layer components {
  @media screen and (min-width: 40rem) {
    @supports (display: grid) {
      @container sidebar (width > 300px) {
        @scope (.card) to (.slot) {
          .title {
            color: rebeccapurple;
            margin: 0 !important;
            content: "/* literal */";
            &:hover { color: navy; }
            @starting-style { opacity: 0; }
            padding: 8px;
          }
        }
      }
    }
  }
}

@custom-at foo { color: red; }
CSS;

	public function testWellFormedStylesheetStructureAndRoundTrip(): void
	{
		$sheet = CSSStyleSheet::parse(self::REPRESENTATIVE_CSS);
		self::assertSame([], $sheet->getParseErrors());

		$rules = $sheet->cssRules;
		self::assertCount(16, $rules);
		self::assertInstanceOf(CSSCharsetRule::class, $rules[0]);
		self::assertInstanceOf(CSSLayerStatementRule::class, $rules[1]);
		self::assertInstanceOf(CSSImportRule::class, $rules[2]);
		self::assertInstanceOf(CSSNamespaceRule::class, $rules[3]);
		self::assertInstanceOf(CSSPropertyRule::class, $rules[4]);
		self::assertInstanceOf(CSSFontFaceRule::class, $rules[5]);
		self::assertInstanceOf(CSSFontFeatureValuesRule::class, $rules[6]);
		self::assertInstanceOf(CSSFontPaletteValuesRule::class, $rules[7]);
		self::assertInstanceOf(CSSCounterStyleRule::class, $rules[8]);
		self::assertInstanceOf(CSSPositionTryRule::class, $rules[9]);
		self::assertInstanceOf(CSSViewTransitionRule::class, $rules[10]);
		self::assertInstanceOf(CSSFunctionRule::class, $rules[11]);
		self::assertInstanceOf(CSSKeyframesRule::class, $rules[12]);
		self::assertInstanceOf(CSSPageRule::class, $rules[13]);
		self::assertInstanceOf(CSSLayerBlockRule::class, $rules[14]);
		self::assertInstanceOf(CSSOpaqueRule::class, $rules[15]);

		// Check deep nested chain + trailing CSSNestedDeclarations
		/** @var CSSLayerBlockRule $layer */
		$layer = $rules[14];
		/** @var CSSMediaRule $media */
		$media = $layer->cssRules[0];
		/** @var CSSSupportsRule $supports */
		$supports = $media->cssRules[0];
		/** @var CSSContainerRule $container */
		$container = $supports->cssRules[0];
		/** @var CSSScopeRule $scope */
		$scope = $container->cssRules[0];
		/** @var CSSStyleRule $styleRule */
		$styleRule = $scope->cssRules[0];

		self::assertInstanceOf(CSSStyleRule::class, $styleRule);
		self::assertSame('rebeccapurple', $styleRule->style->getPropertyValue('color'));
		self::assertSame('important', $styleRule->style->getPropertyPriority('margin'));
		self::assertSame('"/* literal */"', $styleRule->style->getPropertyValue('content'));
		self::assertCount(3, $styleRule->cssRules);
		self::assertInstanceOf(CSSStyleRule::class, $styleRule->cssRules[0]);
		self::assertInstanceOf(CSSStartingStyleRule::class, $styleRule->cssRules[1]);
		self::assertInstanceOf(CSSNestedDeclarations::class, $styleRule->cssRules[2]);

		// Function rule body
		/** @var CSSFunctionRule $fnRule */
		$fnRule = $rules[11];
		self::assertInstanceOf(CSSFunctionDeclarations::class, $fnRule->cssRules[0]);

		$cssText = $sheet->getCssText();
		$pretty = CSSRenderer::render($sheet, new CSSRenderOptions(CSSRenderMode::Pretty));
		$minified = CSSRenderer::render($sheet, new CSSRenderOptions(CSSRenderMode::Minified));

		// Round-trip idempotency for Pretty & Minified
		$reparsedPretty = CSSStyleSheet::parse($pretty);
		self::assertSame([], $reparsedPretty->getParseErrors());
		self::assertSame($pretty, CSSRenderer::render($reparsedPretty, new CSSRenderOptions(CSSRenderMode::Pretty)));

		$reparsedMinified = CSSStyleSheet::parse($minified);
		self::assertSame([], $reparsedMinified->getParseErrors());
		self::assertSame($minified, CSSRenderer::render($reparsedMinified, new CSSRenderOptions(CSSRenderMode::Minified)));
		self::assertSame($cssText, $reparsedPretty->getCssText());

		self::assertSame('@custom-at foo { color: red; }', $rules[15]->getCssText());
	}
}
