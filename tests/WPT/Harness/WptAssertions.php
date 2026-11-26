<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrixReadOnly;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColor;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSHSL;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSHWB;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSLab;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSLCH;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSOKLab;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSOKLCH;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSRGB;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericType;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use LogicException;
use PHPUnit\Framework\Assert;
use Throwable;

/**
 * Domain-aware assertion helpers for WPT conformance suites.
 */
final class WptAssertions
{
	/**
	 * Executes $fn and asserts it throws $expectedClass while never swallowing CSSParserLimitException
	 * (unless explicitly expected) or PHP errors/logic bugs.
	 *
	 * @param class-string<Throwable> $expectedClass
	 */
	public static function assertThrowsException(string $expectedClass, callable $fn, string $context = ''): void
	{
		$caught = null;
		try {
			$fn();
		} catch (CSSParserLimitException $e) {
			if ($expectedClass === CSSParserLimitException::class) {
				$caught = $e;
			} else {
				throw $e;
			}
		} catch (Throwable $e) {
			if ($e instanceof $expectedClass) {
				$caught = $e;
			} else {
				throw $e;
			}
		}

		$message = $context !== ''
			? sprintf('%s (expected exception %s to be thrown)', $context, $expectedClass)
			: sprintf('Expected exception %s to be thrown.', $expectedClass);
		Assert::assertNotNull($caught, $message);
	}

	public static function assertFloatEquals(
		float $expected,
		float $actual,
		float $delta,
		string $context = '',
		bool $checkSignedZero = false,
	): void {
		Assert::assertTrue(is_finite($actual), $context !== '' ? $context . ' must be finite.' : 'Float must be finite.');
		Assert::assertEqualsWithDelta($expected, $actual, $delta, $context);
		if ($checkSignedZero && $expected === 0.0 && $actual === 0.0) {
			Assert::assertSame(
				fdiv(1.0, $expected),
				fdiv(1.0, $actual),
				$context !== '' ? $context . ' (signed zero mismatch)' : 'Signed zero mismatch.',
			);
		}
	}

	/**
	 * @param list<int|float> $expected16
	 */
	public static function assertMatrixElements(
		array $expected16,
		DOMMatrixReadOnly $actual,
		bool $expectedIs2D,
		float $delta = 1e-6,
		string $context = '',
	): void {
		if (count($expected16) !== 16) {
			throw new LogicException('Expected matrix must have exactly 16 elements.');
		}
		Assert::assertSame($expectedIs2D, $actual->is2D(), $context !== '' ? $context . ' (is2D mismatch)' : 'is2D mismatch.');
		$actualElements = $actual->toFloat64Array();
		foreach ($expected16 as $idx => $exp) {
			self::assertFloatEquals(
				(float) $exp,
				$actualElements[$idx],
				$delta,
				sprintf('%s element[%d]', $context, $idx),
			);
		}
	}

	/**
	 * @param array<string, int|string|null> $expectedType
	 */
	public static function assertNumericType(array $expectedType, CSSNumericType $actual, string $context = ''): void
	{
		$actualArray = $actual->toArray();
		$dimensions = ['length', 'angle', 'time', 'frequency', 'resolution', 'flex', 'percent'];
		foreach ($dimensions as $dim) {
			$expVal = (int) ($expectedType[$dim] ?? 0);
			$actVal = (int) ($actualArray[$dim] ?? 0);
			Assert::assertSame($expVal, $actVal, sprintf('%s numeric type dimension "%s"', $context, $dim));
		}
		$expHint = $expectedType['percentHint'] ?? null;
		$actHint = $actualArray['percentHint'] ?? null;
		Assert::assertSame($expHint, $actHint, sprintf('%s numeric type percentHint', $context));
	}

	/**
	 * @param array<string, mixed> $expectedChannels
	 */
	public static function assertColorChannels(
		array $expectedChannels,
		CSSColorValue $actual,
		float $delta = 1e-4,
		string $context = '',
	): void {
		$channelMap = self::extractChannelMap($actual);
		foreach ($expectedChannels as $name => $expectedValue) {
			if (!array_key_exists($name, $channelMap)) {
				throw new LogicException(sprintf('Color class %s does not expose channel "%s".', $actual::class, $name));
			}
			$actualVal = $channelMap[$name];
			if ($expectedValue === 'none') {
				Assert::assertInstanceOf(CSSKeywordValue::class, $actualVal, sprintf('%s channel "%s"', $context, $name));
				Assert::assertSame('none', $actualVal->getValue(), sprintf('%s channel "%s"', $context, $name));
				continue;
			}
			if (is_array($expectedValue)) {
				Assert::assertInstanceOf(CSSUnitValue::class, $actualVal, sprintf('%s channel "%s"', $context, $name));
				if (isset($expectedValue['unit'])) {
					Assert::assertSame((string) $expectedValue['unit'], $actualVal->unit, sprintf('%s channel "%s" unit', $context, $name));
				}
				self::assertFloatEquals((float) $expectedValue['value'], $actualVal->value, $delta, sprintf('%s channel "%s"', $context, $name));
				continue;
			}
			if (is_int($expectedValue) || is_float($expectedValue)) {
				Assert::assertInstanceOf(CSSUnitValue::class, $actualVal, sprintf('%s channel "%s"', $context, $name));
				self::assertFloatEquals((float) $expectedValue, $actualVal->value, $delta, sprintf('%s channel "%s"', $context, $name));
				continue;
			}
			throw new LogicException(sprintf('Invalid expected channel spec for "%s".', $name));
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function extractChannelMap(CSSColorValue $color): array
	{
		return match (true) {
			$color instanceof CSSRGB => ['r' => $color->r, 'g' => $color->g, 'b' => $color->b, 'alpha' => $color->alpha],
			$color instanceof CSSHSL => ['h' => $color->h, 's' => $color->s, 'l' => $color->l, 'alpha' => $color->alpha],
			$color instanceof CSSHWB => ['h' => $color->h, 'w' => $color->w, 'b' => $color->b, 'alpha' => $color->alpha],
			$color instanceof CSSLab => ['l' => $color->l, 'a' => $color->a, 'b' => $color->b, 'alpha' => $color->alpha],
			$color instanceof CSSLCH => ['l' => $color->l, 'c' => $color->c, 'h' => $color->h, 'alpha' => $color->alpha],
			$color instanceof CSSOKLab => ['l' => $color->l, 'a' => $color->a, 'b' => $color->b, 'alpha' => $color->alpha],
			$color instanceof CSSOKLCH => ['l' => $color->l, 'c' => $color->c, 'h' => $color->h, 'alpha' => $color->alpha],
			$color instanceof CSSColor => [
				'0' => $color->channels[0] ?? null,
				'1' => $color->channels[1] ?? null,
				'2' => $color->channels[2] ?? null,
				'alpha' => $color->alpha,
			],
			default => throw new LogicException('Unsupported CSSColorValue subclass: ' . $color::class),
		};
	}
}
