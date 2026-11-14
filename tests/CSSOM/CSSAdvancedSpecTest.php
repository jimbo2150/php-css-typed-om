<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\CSSOM;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSCharsetRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSContainerRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSCounterStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFeatureValuesRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontPaletteValuesRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSGroupingRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSImportRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSMarginRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSNamespaceRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPageRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSScopeRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSViewTransitionRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\StyleSheetList;
use PHPUnit\Framework\TestCase;

final class CSSAdvancedSpecTest extends TestCase
{
	public function testParsesScopeContainerCharsetAndViewTransitionRules(): void
	{
		$css = <<<'CSS'
@charset "UTF-8";
@container sidebar (width > 400px) { .item { display: grid; } }
@scope (.card) to (.slot) { .title { color: navy; } }
@view-transition { navigation: auto; types: slide fade; }
CSS;
		$sheet = CSSStyleSheet::parse($css);
		$rules = $sheet->cssRules;

		self::assertSame([], $sheet->getParseErrors());
		self::assertCount(4, $rules);

		self::assertInstanceOf(CSSCharsetRule::class, $rules[0]);
		self::assertSame('UTF-8', $rules[0]->encoding);
		self::assertSame(CSSRule::CHARSET_RULE, $rules[0]->type);

		self::assertInstanceOf(CSSContainerRule::class, $rules[1]);
		self::assertSame('sidebar', $rules[1]->containerName);
		self::assertSame('(width > 400px)', $rules[1]->containerQuery);
		self::assertSame(CSSRule::CONTAINER_RULE, $rules[1]->type);

		self::assertInstanceOf(CSSScopeRule::class, $rules[2]);
		self::assertSame('.card', $rules[2]->start);
		self::assertSame('.slot', $rules[2]->end);
		self::assertSame(CSSRule::SCOPE_RULE, $rules[2]->type);

		self::assertInstanceOf(CSSViewTransitionRule::class, $rules[3]);
		self::assertSame('auto', $rules[3]->navigation);
		self::assertSame(['slide', 'fade'], $rules[3]->types);
		self::assertSame(CSSRule::VIEW_TRANSITION_RULE, $rules[3]->type);
	}

	public function testParsesComplexImportAndNamespaceMetadata(): void
	{
		$sheet = CSSStyleSheet::parse(
			'@import url("a b.css") layer(base) supports((display: grid) and (color: red)) screen and (min-width: 10px), print;'
			. '@namespace svg url("urn:example:svg namespace");',
		);
		/** @var CSSImportRule $import */
		$import = $sheet->cssRules[0];
		/** @var CSSNamespaceRule $namespace */
		$namespace = $sheet->cssRules[1];

		self::assertSame('a b.css', $import->href);
		self::assertSame('base', $import->layerName);
		self::assertSame('(display: grid) and (color: red)', $import->supportsText);
		self::assertSame('screen and (min-width: 10px), print', $import->mediaText);
		self::assertCount(2, $import->media);
		self::assertNull($import->styleSheet);

		self::assertSame('svg', $namespace->prefix);
		self::assertSame('urn:example:svg namespace', $namespace->namespaceURI);
	}

	public function testPageRuleExtendsGroupingRuleAndManagesMarginBoxes(): void
	{
		$sheet = CSSStyleSheet::parse('@page :first { size: A4; @top-left-corner { content: "A"; } }');
		/** @var CSSPageRule $page */
		$page = $sheet->cssRules[0];

		self::assertInstanceOf(CSSGroupingRule::class, $page);
		self::assertSame(':first', $page->selectorText);
		self::assertSame($page, $page->style->parentRule);
		self::assertCount(1, $page->cssRules);
		self::assertInstanceOf(CSSMarginRule::class, $page->cssRules[0]);
		self::assertCount(1, $page->getMarginRules());

		$page->selectorText = ':left';
		self::assertSame('@page :left { size: A4; @top-left-corner { content: "A"; } }', $page->cssText);
	}

	public function testParsesAndSerializesFontFeatureValuesPaletteAndCounterStyle(): void
	{
		$css = <<<'CSS'
@font-feature-values "Fira Code" {
  @styleset { alt-a: 1 2; }
  @swash { fancy: 3; }
}
@font-palette-values --warm {
  font-family: "Bungee";
  base-palette: 1;
  override-colors: 0 red;
}
@counter-style thumbs {
  system: cyclic;
  symbols: "A";
}
CSS;
		$sheet = CSSStyleSheet::parse($css);
		self::assertSame([], $sheet->getParseErrors());

		/** @var CSSFontFeatureValuesRule $features */
		$features = $sheet->cssRules[0];
		self::assertSame('"Fira Code"', $features->fontFamily);
		self::assertSame([1, 2], $features->styleset->get('alt-a'));
		self::assertSame([['alt-a', [1, 2]]], $features->styleset->entries());
		self::assertSame(['fancy'], $features->swash->keys());
		self::assertSame(1, $features->swash->size);

		/** @var CSSFontPaletteValuesRule $palette */
		$palette = $sheet->cssRules[1];
		self::assertSame('--warm', $palette->name);
		self::assertSame('"Bungee"', $palette->fontFamily);
		self::assertSame('1', $palette->basePalette);
		self::assertSame('0 red', $palette->overrideColors);

		/** @var CSSCounterStyleRule $counter */
		$counter = $sheet->cssRules[2];
		self::assertSame('thumbs', $counter->name);
		self::assertSame('cyclic', $counter->system);
		$counter->suffix = '". "';
		self::assertSame('". "', $counter->getDescriptor('suffix'));

		$reparsed = CSSStyleSheet::parse($sheet->cssText);
		self::assertSame([], $reparsed->getParseErrors());
		self::assertSame($sheet->cssText, $reparsed->cssText);
	}

	public function testReportsExactOffsetsForMalformedAtRuleDescriptorsAndUnclosedComments(): void
	{
		$source = '@font-face { broken; font-family: Test; } /* dangling';
		$sheet = CSSStyleSheet::parse($source);
		$errors = $sheet->getParseErrors();

		self::assertCount(2, $errors);
		self::assertSame(strpos($source, 'broken'), $errors[0]['offset']);
		self::assertSame(strpos($source, '/* dangling'), $errors[1]['offset']);
	}

	public function testStyleSheetMetadataGroupingDefaultIndexAndLinearScale(): void
	{
		$sheet = new CSSStyleSheet('https://example.test/app.css', 'App');
		self::assertSame('text/css', $sheet->type);
		$sheet->insertRule('@media screen { b { color: blue } }');
		/** @var CSSGroupingRule $media */
		$media = $sheet->cssRules[0];
		$media->insertRule('a { color: red }');
		self::assertSame('a', $media->cssRules[0]->selectorText);

		$list = new StyleSheetList([$sheet]);
		self::assertSame($sheet, $list[0]);
		self::assertSame(1, $list->length);

		$rules = [];
		for ($i = 0; $i < 1500; $i++) {
			$rules[] = ".c{$i} { width: {$i}px; }";
		}
		$nested = str_repeat('@media screen { ', 60) . implode("\n", $rules) . str_repeat(' }', 60);
		$start = hrtime(true);
		$largeSheet = CSSStyleSheet::parse($nested);
		$elapsedMs = (hrtime(true) - $start) / 1_000_000;

		self::assertSame([], $largeSheet->getParseErrors());
		self::assertLessThan(1500.0, $elapsedMs);
	}

	public function testStyleRuleTracksParentRuleAndDefaultNestedInsertIndex(): void
	{
		$sheet = CSSStyleSheet::parse('.card { color: black; }');
		/** @var CSSStyleRule $card */
		$card = $sheet->cssRules[0];
		$card->insertRule('&:hover { color: red; }');
		$card->insertRule('&:focus { color: blue; }');

		self::assertSame($card, $card->style->parentRule);
		self::assertSame('&:focus', $card->cssRules[0]->selectorText);
		self::assertSame('&:hover', $card->cssRules[1]->selectorText);
	}
}
