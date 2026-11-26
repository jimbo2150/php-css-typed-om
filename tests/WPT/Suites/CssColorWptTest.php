<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\WPT\Suites;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptAssertions;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptContractTestCase;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptFixtureCase;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptFixtureLoader;
use Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColor;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;

/**
 * WPT conformance suite for css/css-color.
 */
final class CssColorWptTest extends WptContractTestCase
{
	/**
	 * @return array<string, array{WptFixtureCase}>
	 */
	public static function provideCases(): array
	{
		return WptFixtureLoader::loadDomain('css/css-color');
	}

	#[DataProvider('provideCases')]
	public function testConformance(WptFixtureCase $case): void
	{
		$this->assertCase($case, $this->verifyCase(...));
	}

	private function verifyCase(WptFixtureCase $case): void
	{
		match ($case->operation) {
			'color.parse.valid' => $this->runParseValid($case),
			'color.parse.invalid' => $this->runParseInvalid($case),
			'color.serialize' => $this->runParseValid($case),
			'color.convert' => $this->runConvert($case),
			'color.stylevalue.parse' => $this->runStyleValueParse($case),
			default => throw new LogicException(sprintf('Unsupported css/css-color operation "%s" in case "%s".', $case->operation, $case->id)),
		};
	}

	private function runParseValid(WptFixtureCase $case): void
	{
		$cssText = $case->input['cssText'] ?? null;
		if (!is_string($cssText)) {
			throw new LogicException(sprintf('Case "%s" requires string input.cssText.', $case->id));
		}

		$context = $this->buildContextFromInput($case->input);
		$color = CSSColorValue::parse($cssText, null, null, $context);
		$this->assertColorResult($case, $color);
	}

	private function runParseInvalid(WptFixtureCase $case): void
	{
		$cssText = $case->input['cssText'] ?? null;
		if (!is_string($cssText)) {
			throw new LogicException(sprintf('Case "%s" requires string input.cssText.', $case->id));
		}

		$context = $this->buildContextFromInput($case->input);
		WptAssertions::assertThrowsException(
			InvalidArgumentException::class,
			static function () use ($cssText, $context): void {
				CSSColorValue::parse($cssText, null, null, $context);
			},
			$case->id,
		);
	}

	private function runConvert(WptFixtureCase $case): void
	{
		$cssText = $case->input['cssText'] ?? null;
		$steps = $case->input['to'] ?? null;
		if (!is_string($cssText) || (!is_string($steps) && !is_array($steps))) {
			throw new LogicException(sprintf('Case "%s" requires cssText and target space(s) "to".', $case->id));
		}

		$color = CSSColorValue::parse($cssText);
		self::assertInstanceOf(CSSColorValue::class, $color, $case->id);
		$gamutMap = (bool) ($case->input['gamutMap'] ?? false);
		$targetSpaces = is_string($steps) ? [$steps] : $steps;
		foreach ($targetSpaces as $space) {
			if (!is_string($space)) {
				throw new LogicException(sprintf('Case "%s" target space must be string.', $case->id));
			}
			$color = $color->to($space, $gamutMap);
		}

		$this->assertColorResult($case, $color);
	}

	private function runStyleValueParse(WptFixtureCase $case): void
	{
		$cssText = $case->input['cssText'] ?? null;
		if (!is_string($cssText)) {
			throw new LogicException(sprintf('Case "%s" requires string input.cssText.', $case->id));
		}

		$context = $this->buildContextFromInput($case->input);
		$val = CSSStyleValue::parse('color', $cssText, null, $context);
		if (array_key_exists('class', $case->expected)) {
			self::assertSame((string) $case->expected['class'], (new ReflectionClass($val))->getShortName(), $case->id);
		}
		if (array_key_exists('cssText', $case->expected)) {
			self::assertSame((string) $case->expected['cssText'], (string) $val, $case->id);
		}
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private function buildContextFromInput(array $input): ?CSSContext
	{
		$hasScheme = array_key_exists('colorScheme', $input);
		$hasCurrent = array_key_exists('currentColor', $input);
		if (!$hasScheme && !$hasCurrent) {
			return null;
		}
		$scheme = $hasScheme && is_string($input['colorScheme']) ? $input['colorScheme'] : 'light';
		$current = null;
		if ($hasCurrent && is_string($input['currentColor'])) {
			$parsed = CSSColorValue::parse($input['currentColor']);
			self::assertInstanceOf(CSSColorValue::class, $parsed);
			$current = $parsed;
		}

		return new CSSContext(colorScheme: $scheme, currentColor: $current);
	}

	private function assertColorResult(WptFixtureCase $case, CSSColorValue|CSSKeywordValue $color): void
	{
		$expected = $case->expected;
		if (array_key_exists('class', $expected)) {
			self::assertSame((string) $expected['class'], (new ReflectionClass($color))->getShortName(), $case->id);
		}
		if (array_key_exists('colorSpace', $expected)) {
			self::assertInstanceOf(CSSColor::class, $color, $case->id);
			self::assertSame((string) $expected['colorSpace'], $color->colorSpace->value, $case->id);
		}
		if (array_key_exists('cssText', $expected)) {
			self::assertSame((string) $expected['cssText'], (string) $color, $case->id);
		}
		if (isset($expected['channels']) && is_array($expected['channels'])) {
			self::assertInstanceOf(CSSColorValue::class, $color, $case->id);
			/** @var array<string, mixed> $channels */
			$channels = $expected['channels'];
			WptAssertions::assertColorChannels($channels, $color, $case->tolerance ?? 1e-4, $case->id);
		}
	}
}
