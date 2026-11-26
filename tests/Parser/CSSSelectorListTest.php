<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Parser;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSScopeRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSelectorList;
use PHPUnit\Framework\TestCase;

final class CSSSelectorListTest extends TestCase
{
	public function testParsesCanonicallyAndComputesSpecificities(): void
	{
		$list = CSSSelectorList::parse('*.foo, *|*.bar, ns|*.baz, |*.qux, #main > ul li + li.active[data-x=val I]:HOVER::before');
		self::assertCount(5, $list);
		self::assertSame(
			'.foo, .bar, ns|*.baz, |*.qux, #main > ul li + li.active[data-x="val" i]:hover::before',
			$list->getCssText(),
		);
		self::assertSame([
			[0, 1, 0],
			[0, 1, 0],
			[0, 1, 0],
			[0, 1, 0],
			[1, 3, 4],
		], $list->getSpecificities());
		self::assertSame([1, 3, 4], $list->getMaxSpecificity());
		self::assertSame(1, CSSSelectorList::compareSpecificity([1, 0, 0], [0, 99, 99]));
		self::assertSame(0, CSSSelectorList::compareSpecificity([0, 2, 1], [0, 2, 1]));
		self::assertSame(-1, CSSSelectorList::compareSpecificity([0, 1, 2], [0, 2, 0]));
	}

	public function testHandlesSelectorsLevel4FunctionalPseudosAndAnPlusB(): void
	{
		$list = CSSSelectorList::parse(
			':where(#id, .cls), :is(#a, .b, #123bad), :not(.x, div), :has(> .item, + p), '
			. ':nth-child(odd of .row, #top), :nth-last-child(2n - 1), :nth-of-type(0n+3), '
			. ':host(.dark), ::slotted(span.label), ::part(btn icon):hover',
		);

		self::assertSame(
			':where(#id, .cls), :is(#a, .b), :not(.x, div), :has(> .item, + p), '
			. ':nth-child(2n+1 of .row, #top), :nth-last-child(2n-1), :nth-of-type(3), '
			. ':host(.dark), ::slotted(span.label), ::part(btn icon):hover',
			$list->getCssText(),
		);

		self::assertSame([
			[0, 0, 0], // :where
			[1, 0, 0], // :is(#a, .b)
			[0, 1, 0], // :not(.x, div)
			[0, 1, 0], // :has(> .item, + p)
			[1, 1, 0], // :nth-child(odd of .row, #top) = [0,1,0] + [1,0,0]
			[0, 1, 0], // :nth-last-child
			[0, 1, 0], // :nth-of-type
			[0, 2, 0], // :host(.dark) = [0,1,0] + [0,1,0]
			[0, 1, 2], // ::slotted(span.label) = [0,0,1] + [0,1,1]
			[0, 1, 1], // ::part(btn icon):hover
		], $list->getSpecificities());

		// Forgiving empty :is() after dropping invalid items (including pseudo-elements)
		$emptyIs = CSSSelectorList::parse(':is(::before, #123)');
		self::assertSame(':is()', $emptyIs->getCssText());
		self::assertSame([[0, 0, 0]], $emptyIs->getSpecificities());
	}

	public function testNestingAndRelativeSelectorsAndScopeIntegration(): void
	{
		$nested = CSSSelectorList::parseRelative('> .child, & + .sib');
		self::assertTrue($nested->hasLeadingCombinator());
		self::assertSame('> .child, & + .sib', $nested->getCssText());
		self::assertSame([[0, 1, 0], [1, 1, 0]], $nested->getSpecificities([1, 0, 0]));

		$sheet = CSSStyleSheet::parse(<<<'CSS'
.card {
  color: black;
  > .title { color: navy; }
  @media screen { + .sub { color: gray; } }
}
@scope (.panel) to (> .footer, #123bad) {
  > .item { color: red; }
}
CSS);
		self::assertSame([], $sheet->getParseErrors());
		self::assertCount(2, $sheet->cssRules);
		$card = $sheet->cssRules[0];
		self::assertInstanceOf(CSSStyleRule::class, $card);
		$cardTitle = $card->cssRules[0];
		self::assertInstanceOf(CSSStyleRule::class, $cardTitle);
		self::assertSame('> .title', $cardTitle->selectorText);

		$scope = $sheet->cssRules[1];
		self::assertInstanceOf(CSSScopeRule::class, $scope);
		self::assertSame('.panel', $scope->start);
		self::assertSame('> .footer', $scope->end);
		$scopeItem = $scope->cssRules[0];
		self::assertInstanceOf(CSSStyleRule::class, $scopeItem);
		self::assertSame('> .item', $scopeItem->selectorText);

		// Top-level CSSStyleRule rejects leading combinator
		try {
			new CSSStyleRule('> .child');
			self::fail('Expected top-level CSSStyleRule with leading combinator to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// Attaching detached relative rule directly to stylesheet is rejected
		$detachedNested = new CSSStyleRule('> .child', null, CSSParserOptions::DEFAULT_MAX_BYTES, true);
		$this->expectException(InvalidArgumentException::class);
		$sheet->cssRules->append($detachedNested);
	}

	public function testRejectsInvalidSelectorsAndEnforcesDepthLimitsIteratively(): void
	{
		$invalid = [
			'',
			'   ',
			'#123',
			'.123',
			'div > > span',
			'div >',
			'> div',
			'div/*c*/span',
			'[attr=]',
			'[=val]',
			':not()',
			':not(::before)',
			':has()',
			':has(:has(a))',
			':has(::after)',
			':nth-child(2.5n)',
			':nth-of-type(2n of .a)',
			':host(div span)',
			'::slotted(div > span)',
			'::before::after',
			'div*',
			'*div',
			'[data-x="a" z]',
		];

		foreach ($invalid as $bad) {
			try {
				CSSSelectorList::parse($bad);
				self::fail('Expected invalid selector to throw: ' . $bad);
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		$deep = str_repeat(':is(', 300) . 'a' . str_repeat(')', 300);
		try {
			CSSSelectorList::parse($deep, new CSSParserOptions(maxDepth: 64));
			self::fail('Expected deep selector to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// Column combinator (spaced and unspaced), attribute namespaces, and nesting specificity
		$unspaced = CSSSelectorList::parse('col||td, *||td, [|href^="https"], [*|lang="en"]');
		self::assertSame('col || td, * || td, [|href^="https"], [*|lang="en"]', $unspaced->getCssText());

		$nestedSpec = CSSSelectorList::parseRelative('&, &.alt, :is(&, .alt)');
		self::assertSame([[1, 0, 0], [1, 1, 0], [1, 0, 0]], $nestedSpec->getSpecificities([1, 0, 0]));

		// Non-NUL control bytes remain rejected even in browserErrorRecovery mode with comments
		$ctlSheet = CSSStyleSheet::parse("div/*c*/:foo(\x07) { color: red; }", new CSSParserOptions(browserErrorRecovery: true));
		self::assertCount(0, $ctlSheet->cssRules);
		self::assertNotEmpty($ctlSheet->getParseErrors());
	}
}
