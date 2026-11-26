<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\WPT\Suites;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptAssertions;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptContractTestCase;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptFixtureCase;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptFixtureLoader;
use Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathConstant;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * WPT conformance suite for css/css-values.
 */
final class CssValuesWptTest extends WptContractTestCase
{
	/**
	 * @return array<string, array{WptFixtureCase}>
	 */
	public static function provideCases(): array
	{
		return WptFixtureLoader::loadDomain('css/css-values');
	}

	#[DataProvider('provideCases')]
	public function testConformance(WptFixtureCase $case): void
	{
		$this->assertCase($case, $this->verifyCase(...));
	}

	private function verifyCase(WptFixtureCase $case): void
	{
		match ($case->operation) {
			'values.calc.parse' => $this->runCalcParseOrEval($case),
			'values.calc.serialize' => $this->runCalcParseOrEval($case),
			'values.calc.simplify' => $this->runCalcSimplify($case),
			'values.calc.invalid' => $this->runCalcInvalid($case),
			'values.math.eval' => $this->runCalcParseOrEval($case),
			'values.clamp-range' => $this->runClampRange($case),
			default => throw new LogicException(sprintf('Unsupported css/css-values operation "%s" in case "%s".', $case->operation, $case->id)),
		};
	}

	private function runCalcParseOrEval(WptFixtureCase $case): void
	{
		$expr = $case->input['expression'] ?? null;
		if (!is_string($expr)) {
			throw new LogicException(sprintf('Case "%s" requires string input.expression.', $case->id));
		}

		$mode = $case->input['mode'] ?? 'numeric';
		$parsed = match ($mode) {
			'numeric' => CSSNumericValue::parse($expr),
			'calc' => CSSCalcParser::parse($expr),
			'expr' => CSSCalcParser::parseExpression($expr),
			default => throw new LogicException(sprintf('Unsupported parse mode "%s".', (string) $mode)),
		};

		if (isset($case->input['context']) && is_array($case->input['context'])) {
			$parsed = $parsed->simplify($this->buildContext($case->input['context']));
		}

		$this->assertNumericResult($case, $parsed);
	}

	private function runCalcSimplify(WptFixtureCase $case): void
	{
		$expr = $case->input['expression'] ?? null;
		if (!is_string($expr)) {
			throw new LogicException(sprintf('Case "%s" requires string input.expression.', $case->id));
		}

		$parsed = CSSNumericValue::parse($expr);
		$context = isset($case->input['context']) && is_array($case->input['context'])
			? $this->buildContext($case->input['context'])
			: null;
		$simplified = $parsed->simplify($context);

		$this->assertNumericResult($case, $simplified);
	}

	private function runCalcInvalid(WptFixtureCase $case): void
	{
		$expr = $case->input['expression'] ?? null;
		if (!is_string($expr)) {
			throw new LogicException(sprintf('Case "%s" requires string input.expression.', $case->id));
		}

		WptAssertions::assertThrowsException(
			InvalidArgumentException::class,
			static function () use ($expr): void {
				CSSNumericValue::parse($expr);
			},
			$case->id,
		);
	}

	private function runClampRange(WptFixtureCase $case): void
	{
		$expr = $case->input['expression'] ?? null;
		if (!is_string($expr)) {
			throw new LogicException(sprintf('Case "%s" requires string input.expression.', $case->id));
		}
		$min = isset($case->input['min']) ? (float) $case->input['min'] : null;
		$max = isset($case->input['max']) ? (float) $case->input['max'] : null;
		$context = isset($case->input['context']) && is_array($case->input['context'])
			? $this->buildContext($case->input['context'])
			: null;

		$clamped = CSSNumericValue::parse($expr)->clampToRange($min, $max, $context);
		$this->assertNumericResult($case, $clamped);
	}

	private function assertNumericResult(WptFixtureCase $case, CSSNumericValue $actual): void
	{
		$expected = $case->expected;
		$delta = $case->tolerance ?? 1e-9;

		if (array_key_exists('cssText', $expected)) {
			self::assertSame((string) $expected['cssText'], (string) $actual, $case->id);
		}
		if (array_key_exists('value', $expected)) {
			self::assertInstanceOf(CSSUnitValue::class, $actual, $case->id);
			$checkSignedZero = (bool) ($expected['checkSignedZero'] ?? false);
			$expectedVal = ($expected['isNegativeZero'] ?? false) === true ? -0.0 : (float) $expected['value'];
			WptAssertions::assertFloatEquals($expectedVal, $actual->value, $delta, $case->id, $checkSignedZero);
		}
		if (array_key_exists('unit', $expected)) {
			self::assertInstanceOf(CSSUnitValue::class, $actual, $case->id);
			self::assertSame((string) $expected['unit'], $actual->unit, $case->id);
		}
		if (array_key_exists('constant', $expected)) {
			self::assertInstanceOf(CSSMathConstant::class, $actual, $case->id);
			self::assertSame((string) $expected['constant'], $actual->constant->value, $case->id);
		}
		if (isset($expected['type']) && is_array($expected['type'])) {
			WptAssertions::assertNumericType($expected['type'], $actual->type(), $case->id);
		}
		if (($expected['roundTrip'] ?? false) === true) {
			$reparsed = CSSNumericValue::parse((string) $actual);
			self::assertSame((string) $actual, (string) $reparsed, $case->id . ' round-trip');
		}
	}

	/**
	 * @param array<string, mixed> $raw
	 */
	private function buildContext(array $raw): CSSContext
	{
		return new CSSContext(
			fontSize: isset($raw['fontSize']) ? (float) $raw['fontSize'] : 16.0,
			viewportWidth: isset($raw['viewportWidth']) ? (float) $raw['viewportWidth'] : 960.0,
			viewportHeight: isset($raw['viewportHeight']) ? (float) $raw['viewportHeight'] : 540.0,
		);
	}
}
