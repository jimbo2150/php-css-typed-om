<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Grammar;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSGrammarMatcher;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSGrammarNodeType;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSGrammarParser;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueComponentParser;
use PHPUnit\Framework\TestCase;

final class CSSGrammarParserTest extends TestCase
{
	public function testParsesCombinatorsWithSpecPrecedence(): void
	{
		// CSS Values 4 §2.3: juxtaposition > && > || > |
		$ast = CSSGrammarParser::parse('a b && c || d | e');
		self::assertSame(CSSGrammarNodeType::Alternative, $ast->type);
		self::assertCount(2, $ast->children);
		self::assertSame(CSSGrammarNodeType::AnyOrder, $ast->children[0]->type);
		self::assertSame(CSSGrammarNodeType::Keyword, $ast->children[1]->type);

		$allOrder = $ast->children[0]->children[0];
		self::assertSame(CSSGrammarNodeType::AllOrder, $allOrder->type);
		self::assertSame(CSSGrammarNodeType::Sequence, $allOrder->children[0]->type);
		self::assertSame(CSSGrammarNodeType::Keyword, $allOrder->children[1]->type);
	}

	public function testAssignsGloballyUniqueMonotonicNodeIdsAcrossCalls(): void
	{
		$first = CSSGrammarParser::parse('auto | none');
		$second = CSSGrammarParser::parse('auto | none');

		self::assertNotSame($first->id, $second->id);
		self::assertGreaterThan($first->id, $second->id);
	}

	public function testMultipliersAndRequiredGroups(): void
	{
		foreach ([
			['a?', ['a', ''], ['a a']],
			['a*', ['', 'a', 'a a a'], ['b']],
			['a+', ['a', 'a a a'], ['']],
			['a{2,3}', ['a a', 'a a a'], ['a', 'a a a a']],
			['a#', ['a', 'a, a', 'a, a, a'], [', a', 'a,', 'a,,a', 'a a']],
			['a#?', ['', 'a', 'a, a'], ['a,', ',a']],
			['a#{1,2}', ['a', 'a, a'], ['a, a, a', 'a,']],
			['[ a? b? ]!', ['a', 'b', 'a b'], ['', 'c']],
			["'[' <custom-ident>* ']'", ['[]', '[header-start]', '[a b]'], ['a', '[10px]']],
		] as [$grammar, $validCases, $invalidCases]) {
			$node = CSSGrammarParser::parse($grammar);
			$opts = CSSParserOptions::default();

			foreach ($validCases as $css) {
				if ($css === '') {
					continue;
				}
				$parsed = CSSValueComponentParser::parse($css, $opts);
				self::assertNotNull($parsed, "Expected component parse for {$grammar}: {$css}");
				self::assertTrue(
					CSSGrammarMatcher::matches($node, $parsed['source'], $parsed['hasComments'], $parsed['components'], $opts),
					"Expected {$grammar} to match '{$css}'",
				);
			}

			foreach ($invalidCases as $css) {
				if ($css === '') {
					continue;
				}
				$parsed = CSSValueComponentParser::parse($css, $opts);
				$ok = $parsed !== null && CSSGrammarMatcher::matches($node, $parsed['source'], $parsed['hasComments'], $parsed['components'], $opts);
				self::assertFalse($ok, "Expected {$grammar} NOT to match '{$css}'");
			}
		}
	}

	public function testCanonicalizesDimensionalRangeBoundsAndEnforcesLimits(): void
	{
		$angleGrammar = CSSGrammarParser::parse('<angle [-90deg,90deg]>');
		self::assertEqualsWithDelta(-90.0, $angleGrammar->rangeMin, 1e-9);
		self::assertEqualsWithDelta(90.0, $angleGrammar->rangeMax, 1e-9);

		$turnGrammar = CSSGrammarParser::parse('<angle [-0.25turn,0.25turn]>');
		self::assertEqualsWithDelta(-90.0, $turnGrammar->rangeMin, 1e-9);
		self::assertEqualsWithDelta(90.0, $turnGrammar->rangeMax, 1e-9);

		$timeGrammar = CSSGrammarParser::parse('<time [500ms,2s]>');
		self::assertEqualsWithDelta(0.5, $timeGrammar->rangeMin, 1e-9);
		self::assertEqualsWithDelta(2.0, $timeGrammar->rangeMax, 1e-9);

		$resGrammar = CSSGrammarParser::parse('<resolution [1x,192dpi]>');
		self::assertEqualsWithDelta(1.0, $resGrammar->rangeMin, 1e-9);
		self::assertEqualsWithDelta(2.0, $resGrammar->rangeMax, 1e-9);

		$propRef = CSSGrammarParser::parse("<'border-top-width'>");
		self::assertSame(CSSGrammarNodeType::NonTerminal, $propRef->type);
		self::assertSame('@prop:border-top-width', $propRef->value);

		foreach ([
			'',
			'[ a',
			'a |',
			'| a',
			'[]',
			'<unknown [0,1]>',
			'<number [5,1]>',
			str_repeat('[ ', CSSGrammarParser::MAX_GRAMMAR_DEPTH + 2) . 'a' . str_repeat(' ]', CSSGrammarParser::MAX_GRAMMAR_DEPTH + 2),
			implode(' | ', array_fill(0, CSSGrammarParser::MAX_GRAMMAR_NODES + 5, 'a')),
		] as $badGrammar) {
			try {
				CSSGrammarParser::parse($badGrammar);
				self::fail('Expected invalid grammar to throw InvalidArgumentException: ' . substr($badGrammar, 0, 40));
			} catch (InvalidArgumentException) {
			}
		}
	}
}
