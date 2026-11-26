<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSCharsetRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSCustomMediaRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSImportRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSLayerStatementRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSMediaRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSNamespaceRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSScopeRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use LogicException;
use PHPUnit\Framework\TestCase;

final class CSSRuleOrderTest extends TestCase
{
	public function testParseStylesheetEnforcesTopLevelRuleOrdering(): void
	{
		$sheet = CSSStyleSheet::parse(
			'@charset "UTF-8"; @layer reset; @import url("a.css"); @layer base; @namespace svg url("urn:svg"); body { color: red; } @import url("late.css"); @namespace x url("urn:x"); @charset "ISO-8859-1";',
		);

		self::assertCount(6, $sheet->cssRules);
		self::assertInstanceOf(CSSCharsetRule::class, $sheet->cssRules[0]);
		self::assertInstanceOf(CSSLayerStatementRule::class, $sheet->cssRules[1]);
		self::assertInstanceOf(CSSImportRule::class, $sheet->cssRules[2]);
		self::assertInstanceOf(CSSLayerStatementRule::class, $sheet->cssRules[3]);
		self::assertInstanceOf(CSSNamespaceRule::class, $sheet->cssRules[4]);
		self::assertInstanceOf(CSSStyleRule::class, $sheet->cssRules[5]);
		self::assertCount(3, $sheet->getParseErrors());

		$lateImportAfterNamespace = CSSStyleSheet::parse('@namespace svg url("urn:svg"); @import url("bad.css");');
		self::assertCount(1, $lateImportAfterNamespace->cssRules);
		self::assertInstanceOf(CSSNamespaceRule::class, $lateImportAfterNamespace->cssRules[0]);
		self::assertCount(1, $lateImportAfterNamespace->getParseErrors());

		$lateCharsetAfterLayer = CSSStyleSheet::parse('@layer base; @charset "UTF-8";');
		self::assertCount(1, $lateCharsetAfterLayer->cssRules);
		self::assertInstanceOf(CSSLayerStatementRule::class, $lateCharsetAfterLayer->cssRules[0]);
		self::assertCount(1, $lateCharsetAfterLayer->getParseErrors());
	}

	public function testParseStylesheetRejectsTopLevelOnlyStatementsInsideGroupingRules(): void
	{
		$sheet = CSSStyleSheet::parse(
			'@media screen { @import url("nested.css"); @namespace x url("urn:x"); @charset "UTF-8"; @custom-media --sm (width < 500px); @layer inner; a { color: red; } }',
		);

		self::assertCount(1, $sheet->cssRules);
		$media = $sheet->cssRules[0];
		self::assertInstanceOf(CSSMediaRule::class, $media);
		self::assertCount(2, $media->cssRules);
		self::assertInstanceOf(CSSLayerStatementRule::class, $media->cssRules[0]);
		self::assertInstanceOf(CSSStyleRule::class, $media->cssRules[1]);
		self::assertCount(4, $sheet->getParseErrors());
	}

	public function testDeleteRuleThrowsLogicExceptionWhenDeletingNamespaceWhileNonPreludeRulesExist(): void
	{
		$sheet = CSSStyleSheet::parse('@import url("a.css"); @namespace svg url("urn:svg"); svg|rect { fill: blue; }');

		try {
			$sheet->deleteRule(1);
			self::fail('Expected LogicException when deleting @namespace with active style rule.');
		} catch (LogicException $exception) {
			self::assertStringContainsString('@namespace', $exception->getMessage());
		}

		try {
			$sheet->cssRules->remove(1);
			self::fail('Expected LogicException from CSSRuleList::remove() when deleting @namespace.');
		} catch (LogicException $exception) {
			self::assertStringContainsString('@namespace', $exception->getMessage());
		}

		$sheet->deleteRule(2);
		$sheet->deleteRule(1);
		self::assertCount(1, $sheet->cssRules);
		self::assertInstanceOf(CSSImportRule::class, $sheet->cssRules[0]);
	}

	public function testCssRuleListDirectMutationsEnforceHierarchyAndRelativeSelectors(): void
	{
		$sheet = CSSStyleSheet::parse('@charset "UTF-8"; body { color: black; }');

		$this->assertThrowsInvalidArgument(static fn () => $sheet->cssRules->insert(0, new CSSCharsetRule('"UTF-8"')));
		$this->assertThrowsInvalidArgument(static fn () => $sheet->cssRules->insert(2, new CSSImportRule('url("a.css")')));
		$this->assertThrowsInvalidArgument(static fn () => $sheet->cssRules->append(new CSSNamespaceRule('svg url("urn:svg")')));
		$this->assertThrowsInvalidArgument(
			static fn () => $sheet->cssRules->append(new CSSStyleRule('> span', null, CSSParserOptions::DEFAULT_MAX_BYTES, true)),
		);

		$mediaSheet = CSSStyleSheet::parse('@media screen { .card { color: red; } }');
		/** @var CSSMediaRule $media */
		$media = $mediaSheet->cssRules[0];
		$this->assertThrowsInvalidArgument(static fn () => $media->cssRules->append(new CSSCustomMediaRule('--x true')));
		$this->assertThrowsInvalidArgument(
			static fn () => $media->cssRules->append(new CSSStyleRule('> .child', null, CSSParserOptions::DEFAULT_MAX_BYTES, true)),
		);
		$this->assertThrowsInvalidArgument(static fn () => $media->insertRule('@custom-media --y (width > 10px);', 0));
		$this->assertThrowsInvalidArgument(static fn () => $media->insertRule('> .child { color: red; }', 0));

		// Relative leading-combinator rule inside @media nested within CSSStyleRule or @scope IS valid
		$scopeSheet = CSSStyleSheet::parse('@scope (.card) { @media screen { } }');
		/** @var CSSScopeRule $scope */
		$scope = $scopeSheet->cssRules[0];
		/** @var CSSMediaRule $nestedMedia */
		$nestedMedia = $scope->cssRules[0];
		$nestedMedia->cssRules->append(new CSSStyleRule('> .title', null, CSSParserOptions::DEFAULT_MAX_BYTES, true));
		self::assertCount(1, $nestedMedia->cssRules);
		$childStyle = $nestedMedia->cssRules[0];
		self::assertInstanceOf(CSSStyleRule::class, $childStyle);
		self::assertSame('> .title', $childStyle->selectorText);
	}

	public function testCssRuleListReplaceAllEnforcesHierarchyAndOrdering(): void
	{
		$preludeSheet = CSSStyleSheet::parse('@import url("a.css"); @custom-media --m (width > 10px);');
		$mediaSheet = CSSStyleSheet::parse('@media screen { .ok { color: red; } }');
		/** @var CSSMediaRule $mediaRule */
		$mediaRule = $mediaSheet->cssRules[0];
		$this->assertThrowsInvalidArgument(static fn () => $mediaRule->cssRules->replaceAll($preludeSheet->cssRules));
		self::assertCount(1, $mediaRule->cssRules);

		$scopeSheet = CSSStyleSheet::parse('@scope (.card) { > .inner { color: blue; } }');
		/** @var CSSScopeRule $scopeRule */
		$scopeRule = $scopeSheet->cssRules[0];
		$topSheet = CSSStyleSheet::parse('body { color: black; }');
		$this->assertThrowsInvalidArgument(static fn () => $topSheet->cssRules->replaceAll(clone $scopeRule->cssRules));
		self::assertCount(1, $topSheet->cssRules);
		$this->assertThrowsInvalidArgument(static fn () => $mediaRule->cssRules->replaceAll(clone $scopeRule->cssRules));
		self::assertCount(1, $mediaRule->cssRules);
	}

	private function assertThrowsInvalidArgument(callable $fn): void
	{
		try {
			$fn();
			self::fail('Expected InvalidArgumentException.');
		} catch (InvalidArgumentException $exception) {
			self::assertNotSame('', $exception->getMessage());
		}
	}
}
