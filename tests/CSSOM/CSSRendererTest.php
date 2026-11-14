<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\CSSOM;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFeatureValuesRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderer;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderMode;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use PHPUnit\Framework\TestCase;

final class CSSRendererTest extends TestCase
{
	public function testRendersPrettyAndMinifiedNestedRules(): void
	{
		$sheet = CSSStyleSheet::parse('@media screen { a { color: red; margin: 0 !important; &:hover { color: blue } } }');

		self::assertSame([], $sheet->getParseErrors());
		self::assertSame(<<<'CSS'
@media screen {
  a {
    color: red;
    margin: 0 !important;
    &:hover {
      color: blue;
    }
  }
}
CSS, CSSRenderer::render($sheet));
		self::assertSame(
			'@media screen{a{color:red;margin:0!important;&:hover{color:blue;}}}',
			CSSRenderer::render($sheet, new CSSRenderOptions(CSSRenderMode::Minified)),
		);

		$roundTrip = CSSStyleSheet::parse(CSSRenderer::render($sheet, new CSSRenderOptions(CSSRenderMode::Minified)));
		self::assertSame([], $roundTrip->getParseErrors());
		self::assertSame('blue', $roundTrip->cssRules[0]->cssRules[0]->cssRules[0]->style->getPropertyValue('color'));
	}

	public function testRendersStandaloneRulesAndStructuredAtRules(): void
	{
		$sheet = CSSStyleSheet::parse('@import url("theme.css") screen; @font-face { font-family: "Demo"; src: url(demo.woff2); }');
		self::assertSame([], $sheet->getParseErrors());

		self::assertSame('@import url("theme.css") screen;', CSSRenderer::render($sheet->cssRules[0], new CSSRenderOptions(CSSRenderMode::Minified)));
		self::assertSame(
			"@font-face {\n  font-family: \"Demo\";\n  src: url(demo.woff2);\n}",
			CSSRenderer::render($sheet->cssRules[1]),
		);
	}

	public function testRendersFeatureValueMapsAndKeyframes(): void
	{
		$sheet = CSSStyleSheet::parse(<<<'CSS'
@font-feature-values "Fira Code" {
  @styleset { alt-a: 1 2; }
}
@keyframes pulse {
  from { opacity: 0; }
  to { opacity: 1; }
}
CSS);
		self::assertSame([], $sheet->getParseErrors());
		self::assertInstanceOf(CSSFontFeatureValuesRule::class, $sheet->cssRules[0]);

		self::assertSame(<<<'CSS'
@font-feature-values "Fira Code" {
  @styleset {
    alt-a: 1 2;
  }
}
@keyframes pulse {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}
CSS, CSSRenderer::render($sheet));
		self::assertSame(
			'@font-feature-values "Fira Code"{@styleset{alt-a:1 2;}}@keyframes pulse{from{opacity:0;}to{opacity:1;}}',
			CSSRenderer::render($sheet, new CSSRenderOptions(CSSRenderMode::Minified)),
		);
	}

	public function testRendersPageGroupingAndDescriptorRules(): void
	{
		$sheet = CSSStyleSheet::parse(<<<'CSS'
@layer theme {
  @supports (display: grid) {
    .card { display: grid; }
  }
}
@page :first {
  size: A4;
  @top-left-corner { content: "A"; }
}
@counter-style thumbs {
  system: cyclic;
  symbols: "A";
}
CSS);
		self::assertSame([], $sheet->getParseErrors());

		$rendered = CSSRenderer::render($sheet);
		self::assertStringContainsString("@layer theme {\n  @supports (display: grid) {", $rendered);
		self::assertStringContainsString("@page :first {\n  size: A4;\n  @top-left-corner {\n    content: \"A\";\n  }\n}", $rendered);
		self::assertStringContainsString("@counter-style thumbs {\n  system: cyclic;\n  symbols: \"A\";\n}", $rendered);

		$roundTrip = CSSStyleSheet::parse(CSSRenderer::render($sheet, new CSSRenderOptions(CSSRenderMode::Minified)));
		self::assertSame([], $roundTrip->getParseErrors());
	}

	public function testRendersNestedDeclarationsAndFunctionRuleBodies(): void
	{
		$sheet = CSSStyleSheet::parse(<<<'CSS'
a {
  color: black;
  &:hover { color: blue; }
  color: green;
}
@function --lighter(--color <color>, --amount <number>: 0.2) returns <color> {
  --local: 1;
}
CSS);
		self::assertSame([], $sheet->getParseErrors());

		$pretty = CSSRenderer::render($sheet);
		self::assertStringContainsString("  &:hover {\n    color: blue;\n  }\n  color: green;", $pretty);
		self::assertStringContainsString("@function --lighter(--color <color>, --amount <number>: 0.2) returns <color> {\n  --local: 1;\n}", $pretty);

		$minified = CSSRenderer::render($sheet, new CSSRenderOptions(CSSRenderMode::Minified));
		self::assertStringContainsString('a{color:black;&:hover{color:blue;}color:green;}', $minified);
		self::assertStringContainsString('@function --lighter(--color <color>, --amount <number>: 0.2) returns <color>{--local:1;}', $minified);
		self::assertSame([], CSSStyleSheet::parse($minified)->getParseErrors());
	}

	public function testRuleCallbackRunsPostOrderAndTransformsChildOutput(): void
	{
		$sheet = CSSStyleSheet::parse('a { color: red; &:hover { color: blue; } }');
		$visited = [];
		$options = new CSSRenderOptions(
			ruleCallback: static function (CSSStyleRule $rule, string $text) use (&$visited): string {
				$visited[] = $rule->getSelectorText();

				return str_replace('color: blue;', 'color: green;', $text);
			},
		);

		$rendered = CSSRenderer::render($sheet, $options);

		self::assertSame(['&:hover', 'a'], $visited);
		self::assertStringContainsString('color: green;', $rendered);
		self::assertStringNotContainsString('color: blue;', $rendered);
	}

	public function testOpaqueSourceIsPreservedAndLegacySerializationIsUnchanged(): void
	{
		$source = '@future-rule x { arbitrary:  a; }';
		$sheet = CSSStyleSheet::parse($source.' a { color: red }');
		$opaque = $sheet->cssRules[0];

		self::assertSame($source, CSSRenderer::render($opaque));
		self::assertSame($source, CSSRenderer::render($opaque, new CSSRenderOptions(CSSRenderMode::Minified)));
		self::assertSame("@future-rule x { arbitrary:  a; }\na { color: red; }", $sheet->getCssText());
		self::assertSame('a{color:red;}', CSSRenderer::render($sheet->cssRules[1], new CSSRenderOptions(CSSRenderMode::Minified)));
	}
}
