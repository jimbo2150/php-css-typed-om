<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\CSSOM;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFeatureValuesRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSMediaRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSOpaqueRule;
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
		$media = $roundTrip->cssRules[0];
		self::assertInstanceOf(CSSMediaRule::class, $media);
		$styleRule = $media->cssRules[0];
		self::assertInstanceOf(CSSStyleRule::class, $styleRule);
		$nestedRule = $styleRule->cssRules[0];
		self::assertInstanceOf(CSSStyleRule::class, $nestedRule);
		self::assertSame('blue', $nestedRule->style->getPropertyValue('color'));
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
			ruleCallback: static function (\Jimbo2150\PhpCssTypedOm\CSSOM\CSSRule $rule, string $text) use (&$visited): string {
				if ($rule instanceof CSSStyleRule) {
					$visited[] = $rule->getSelectorText();
				}

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

	public function testKeepCommentsPreservesOpaqueComments(): void
	{
		$source = '@future-rule x { /* retained */ arbitrary: a; }';
		$sheet = CSSStyleSheet::parse($source);
		$opaque = $sheet->cssRules[0];

		self::assertSame($source, CSSRenderer::render($opaque));
		self::assertSame($source, CSSRenderer::render($opaque, new CSSRenderOptions(CSSRenderMode::Pretty)));
		self::assertSame($source, CSSRenderer::render($opaque, new CSSRenderOptions(CSSRenderMode::Minified)));
	}

	public function testKeepCommentsFalseStripsOpaqueComments(): void
	{
		$source = '@future-rule x { /* retained */ arbitrary: a; }';
		$sheet = CSSStyleSheet::parse($source);
		$opaque = $sheet->cssRules[0];

		$pretty = CSSRenderer::render($opaque, new CSSRenderOptions(CSSRenderMode::Pretty, null, false));
		$minified = CSSRenderer::render($opaque, new CSSRenderOptions(CSSRenderMode::Minified, null, false));

		self::assertStringNotContainsString('/* retained */', $pretty);
		self::assertStringNotContainsString('/* retained */', $minified);
		self::assertStringContainsString('arbitrary: a;', $pretty);
		self::assertStringContainsString('arbitrary: a;', $minified);
	}

	public function testMinifiedStructuredOutputHasNoNewlines(): void
	{
		$sheet = CSSStyleSheet::parse('@media screen { a { color: red; &:hover { color: blue; } } }');
		$minified = CSSRenderer::render($sheet, new CSSRenderOptions(CSSRenderMode::Minified));

		self::assertStringNotContainsString("\n", $minified);
		self::assertSame([], CSSStyleSheet::parse($minified)->getParseErrors());
	}

	public function testMinifiedOutputStripsNewlinesInCommaSeparatedSelectors(): void
	{
		$sheet = CSSStyleSheet::parse("h1,\nh2 {\n  color: red;\n}\n@media screen {\n  p,\n  div {\n    color: blue;\n  }\n}");
		$minified = CSSRenderer::render($sheet, new CSSRenderOptions(CSSRenderMode::Minified));

		self::assertStringNotContainsString("\n", $minified);
		self::assertStringContainsString('h1,h2{color:red;}', $minified);
		self::assertStringContainsString('@media screen{p,', $minified);
		self::assertStringContainsString('div{color:blue;}}', $minified);
		self::assertSame([], CSSStyleSheet::parse($minified)->getParseErrors());
	}

	public function testRendersEmptyPrettyBlocksDescendantNewlinesOmittedCallbacksAndNestedOpaque(): void
	{
		$sheet = CSSStyleSheet::parse("a {}\ndiv\nspan { color: red; }\n@media screen { @unknown-nested { x: 1; } }");
		self::assertSame([], $sheet->getParseErrors());

		$pretty = CSSRenderer::render($sheet);
		self::assertStringContainsString("a {}\n", $pretty);
		self::assertStringContainsString("  @unknown-nested { x: 1; }\n}", $pretty);

		$minified = CSSRenderer::render($sheet, new CSSRenderOptions(CSSRenderMode::Minified));
		self::assertStringContainsString('a{}div span{color:red;}', $minified);

		$filtered = CSSRenderer::render($sheet, new CSSRenderOptions(
			ruleCallback: static fn ($rule, string $text): string => $rule instanceof CSSOpaqueRule ? '' : $text,
		));
		self::assertSame("a {}\ndiv\nspan {\n  color: red;\n}\n@media screen {}", $filtered);
	}

	public function testMinifiedPreservesHexEscapeTerminatingWhitespaceAndEscapesStyleCloseTag(): void
	{
		$sheet = CSSStyleSheet::parse(".a\\2f  b { content: \"</style>\"; }");
		self::assertSame([], $sheet->getParseErrors());

		$minified = CSSRenderer::render($sheet, new CSSRenderOptions(CSSRenderMode::Minified, escapeHtmlStyleTag: true));
		self::assertSame('.a\2f  b{content:"<\/style>";}', $minified);

		$edgeSheet = CSSStyleSheet::parse(".a\\2f\r\nb[title=\u{00E9}url(a   b)] { color: red; }");
		self::assertSame([], $edgeSheet->getParseErrors());
		$edgeMinified = CSSRenderer::render($edgeSheet, new CSSRenderOptions(CSSRenderMode::Minified));
		self::assertSame('.a\2f b[title=éurl(a b)]{color:red;}', $edgeMinified);
	}

	public function testHandlesDepth256AndPreservesSelectorCommentBoundariesInMinify(): void
	{
		$deepCss = str_repeat('@media screen {', 255) . 'a { color: red; }' . str_repeat('}', 255);
		$deepSheet = CSSStyleSheet::parse($deepCss);
		self::assertSame([], $deepSheet->getParseErrors());
		self::assertStringContainsString('color: red;', $deepSheet->getCssText());
		self::assertStringContainsString('a{color:red;}', CSSRenderer::render($deepSheet, new CSSRenderOptions(CSSRenderMode::Minified)));

		$deepStyleCss = str_repeat('a { ', 256) . 'color: red;' . str_repeat('}', 256);
		$deepStyleSheet = CSSStyleSheet::parse($deepStyleCss);
		self::assertSame([], $deepStyleSheet->getParseErrors());
		self::assertStringContainsString('color: red;', $deepStyleSheet->getCssText());

		$commentSel = CSSStyleSheet::parse('div/*c*/span { color: red; }');
		self::assertSame('div span{color:red;}', CSSRenderer::render($commentSel, new CSSRenderOptions(CSSRenderMode::Minified, keepComments: false)));
	}
}
