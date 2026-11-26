<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Grammar;

use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSDataTypeMatcher;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSGrammarMatcher;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSGrammarParser;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSPropertyGrammarRegistry;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueComponentParser;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueValidator;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use PHPUnit\Framework\TestCase;

final class CSSGrammarPhase2RegressionTest extends TestCase
{
	protected function tearDown(): void
	{
		CSS::clearRegisteredProperties();
		CSSValueValidator::clearCacheInternal();
	}

	public function testGuardB1StrictDepthStillEnforcedAfterDefaultWarmupWhileFlatHitsCache(): void
	{
		CSSValueValidator::clearCacheInternal();
		$default = CSSParserOptions::default();

		// Warm 2-level cache with nested expressions and flat multi-token value under default limits
		self::assertTrue(CSSValueValidator::validateStandardProperty('width', 'calc(1px + calc(2px + calc(3px + 4px)))', $default));
		self::assertTrue(CSSValueValidator::validateStandardProperty('margin', '10px 20px', $default));
		self::assertTrue(CSSValueValidator::validateStandardProperty('color', 'rgb(calc(1 + 2), 0, 0)', $default));

		// Flat multi-token value must succeed under strict maxDepth = 1
		$strictDepth = new CSSParserOptions(maxDepth: 1, maxMathDepth: 1);
		self::assertTrue(CSSValueValidator::validateStandardProperty('margin', '10px 20px', $strictDepth));

		// Nested parens must NOT bypass maxDepth via the default cache
		try {
			CSSValueValidator::validateStandardProperty('color', 'rgb(calc(1 + 2), 0, 0)', $strictDepth);
			self::fail('Expected CSSParserLimitException for nested function under maxDepth=1 after warm-up.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// Nested calc exceeding maxMathDepth=1 (mathDepth=2) must NOT bypass via the default cache
		$strictMath = new CSSParserOptions(maxMathDepth: 1);
		$this->expectException(CSSParserLimitException::class);
		CSSValueValidator::validateStandardProperty('width', 'calc(1px + calc(2px + calc(3px + 4px)))', $strictMath);
	}

	public function testGuardB1ValidationCacheEvictionAtLimit(): void
	{
		CSSValueValidator::clearCacheInternal();
		$default = CSSParserOptions::default();

		// Fill beyond MAX_CACHE_ENTRIES (2_048) with cacheable flat multi-token values
		for ($i = 1; $i <= 2100; $i++) {
			self::assertTrue(
				CSSValueValidator::validateStandardProperty('margin', $i . 'px ' . ($i + 1) . 'px', $default),
			);
		}

		// Re-validating works cleanly after eviction
		self::assertTrue(CSSValueValidator::validateStandardProperty('margin', '10px 20px', $default));
		self::assertFalse(CSSValueValidator::validateStandardProperty('margin', '10px not-a-unit', $default));
	}

	public function testGuardM1AlternativeKeywordCaseSensitivity(): void
	{
		$node = CSSGrammarParser::parse('Auto | None', true);
		$opts = CSSParserOptions::default();

		$matches = static function (string $value) use ($node, $opts): bool {
			$parsed = CSSValueComponentParser::parse($value, $opts);
			self::assertNotNull($parsed);

			return CSSGrammarMatcher::matches(
				$node,
				$parsed['source'],
				$parsed['hasComments'],
				$parsed['components'],
				$opts,
			);
		};

		self::assertTrue($matches('Auto'));
		self::assertTrue($matches('None'));
		self::assertFalse($matches('auto'));
		self::assertFalse($matches('AUTO'));
		self::assertFalse($matches('none'));
	}

	public function testGuardM1AlternativePreservesDuplicateFunctionNamesAndFallbackSequences(): void
	{
		$opts = CSSParserOptions::default();

		// Duplicate function names in Alternative: foo(<length>) | foo(<color>)
		$fnGrammar = CSSGrammarParser::parse('foo( <length> ) | foo( <color> )');
		foreach (['foo(10px)' => true, 'foo(rebeccapurple)' => true, 'foo(10s)' => false] as $val => $expected) {
			$parsed = CSSValueComponentParser::parse($val, $opts);
			self::assertNotNull($parsed);
			self::assertSame(
				$expected,
				CSSGrammarMatcher::matches($fnGrammar, $parsed['source'], $parsed['hasComments'], $parsed['components'], $opts),
				$val,
			);
		}

		// Keyword indexed in keywordMap must NOT short-circuit longer Sequence alternatives: auto | auto <length>
		$seqGrammar = CSSGrammarParser::parse('auto | auto <length>');
		foreach (['auto' => true, 'auto 10px' => true, 'auto red' => false] as $val => $expected) {
			$parsed = CSSValueComponentParser::parse($val, $opts);
			self::assertNotNull($parsed);
			self::assertSame(
				$expected,
				CSSGrammarMatcher::matches($seqGrammar, $parsed['source'], $parsed['hasComments'], $parsed['components'], $opts),
				$val,
			);
		}
	}

	public function testZeroCopyMathEvaluationAndTransitionTimeOrderingShareEvalCache(): void
	{
		CSSValueValidator::clearCacheInternal();

		// Transition and animation validateTimeOrdering with calc() durations/delays
		self::assertTrue(CSSValueValidator::validateStandardProperty('transition', 'opacity calc(1s + 500ms) ease calc(200ms + 100ms)'));
		self::assertTrue(CSSValueValidator::validateStandardProperty('transition', '100ms calc(1s + 1s) all'));
		self::assertFalse(CSSValueValidator::validateStandardProperty('transition', 'opacity calc(1s + 500px) ease'));
		self::assertTrue(CSSValueValidator::validateStandardProperty('animation', 'calc(1s + 1s) ease-in calc(0.5s * 2) slide-in'));

		// Zero-copy math evaluation inside arbitrary property values
		self::assertTrue(CSSValueValidator::validateStandardProperty('width', 'calc(10px + 20px)'));
		self::assertFalse(CSSValueValidator::validateStandardProperty('width', 'calc(10px + 20s)'));
	}

	public function testSharedMathBudgetEnforcedAcrossRepeatedMathCalls(): void
	{
		$opts = CSSParserOptions::default();
		$grammar = CSSPropertyGrammarRegistry::getGrammar('<length>+', true);
		$parsed = CSSValueComponentParser::parse('calc(1px + 2px) calc(3px + 4px)', $opts);
		self::assertNotNull($parsed);

		// Verify cumulative budget accumulation across multiple calc() items and leaf math calls
		$evalCache = [];
		$mathBudget = 0;
		$steps = 0;
		self::assertTrue(CSSGrammarMatcher::matches(
			$grammar,
			$parsed['source'],
			$parsed['hasComments'],
			$parsed['components'],
			$opts,
			$steps,
			$evalCache,
			$mathBudget,
		));
		self::assertGreaterThan(10, $mathBudget);

		// Also verify direct evaluateMathComponentInternal + matcher share and enforce MAX_MATH_NODES
		$nearCap = CSSNumericValue::MAX_MATH_NODES - 10;
		$evalCache2 = [];
		$steps2 = 0;
		try {
			CSSGrammarMatcher::matches(
				$grammar,
				$parsed['source'],
				$parsed['hasComments'],
				$parsed['components'],
				$opts,
				$steps2,
				$evalCache2,
				$nearCap,
			);
			self::fail('Expected CSSParserLimitException from CSSGrammarMatcher::matches near MAX_MATH_NODES.');
		} catch (CSSParserLimitException) {
			$this->addToAssertionCount(1);
		}

		$evalCache3 = [];
		$nearCapDirect = CSSNumericValue::MAX_MATH_NODES - 1;
		$this->expectException(CSSParserLimitException::class);
		CSSDataTypeMatcher::evaluateMathComponentInternal($parsed['source'], $parsed['components'][0], $opts, $evalCache3, $nearCapDirect);
	}

	public function testMixedCaseKeywordPropertiesAreRecognized(): void
	{
		self::assertTrue(CSSValueValidator::validateStandardProperty('text-rendering', 'optimizeLegibility'));
		self::assertTrue(CSSValueValidator::validateStandardProperty('text-rendering', 'geometricPrecision'));
		self::assertTrue(CSSValueValidator::validateStandardProperty('text-rendering', 'optimizeSpeed'));
		self::assertTrue(CSSValueValidator::validateStandardProperty('text-rendering', 'optimizelegibility'));
		self::assertTrue(CSSValueValidator::validateStandardProperty('shape-rendering', 'crispEdges'));
		self::assertTrue(CSSValueValidator::validateStandardProperty('shape-rendering', 'geometricPrecision'));
		self::assertTrue(CSSValueValidator::validateStandardProperty('pointer-events', 'visiblePainted'));
		self::assertTrue(CSSValueValidator::validateStandardProperty('pointer-events', 'visibleFill'));
		self::assertTrue(CSSValueValidator::validateStandardProperty('pointer-events', 'visibleStroke'));
		self::assertTrue(CSSValueValidator::validateStandardProperty('pointer-events', 'bounding-box'));
		self::assertFalse(CSSValueValidator::validateStandardProperty('text-rendering', 'notAKeyword'));
	}

	public function testCustomSyntaxGrammarCacheReusesDeduplicatedEntry(): void
	{
		CSSValueValidator::clearCacheInternal();

		CSS::registerProperty([
			'name' => '--phase2-cached-syntax',
			'syntax' => '<length> | <color>',
			'inherits' => false,
			'initialValue' => '0px',
		]);

		self::assertFalse(CSSPropertyGrammarRegistry::hasCachedGrammar('<length> | <color>', true));
		self::assertTrue(CSSValueValidator::validatePropertyValue('--phase2-cached-syntax', '12px', null, true));
		self::assertTrue(CSSPropertyGrammarRegistry::hasCachedGrammar('<length> | <color>', true));
		self::assertTrue(CSSValueValidator::validatePropertyValue('--phase2-cached-syntax', 'rgb(10, 20, 30)', null, true));
		self::assertFalse(CSSValueValidator::validatePropertyValue('--phase2-cached-syntax', '10s', null, true));
	}
}
