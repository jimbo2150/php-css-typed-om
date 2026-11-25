<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathValue;
use ReflectionClass;

/**
 * Represents a CSS `color(<colorspace> c1 c2 c3 [/ alpha])` value.
 */
final class CSSColor extends CSSColorValue
{
	public const int MAX_CUSTOM_CHANNELS = 64;

	/** @var array<string, true> */
	public const array PREDEFINED_SPACES = [
		'srgb' => true,
		'srgb-linear' => true,
		'display-p3' => true,
		'a98-rgb' => true,
		'prophoto-rgb' => true,
		'rec2020' => true,
		'xyz' => true,
		'xyz-d50' => true,
		'xyz-d65' => true,
	];

	private CSSKeywordValue $colorSpaceValue;

	/** @var ?list<CSSNumericValue|CSSKeywordValue> */
	private ?array $channelsValue = null;

	public CSSKeywordValue $colorSpace {
		get => $this->colorSpaceValue->clone();
		set (CSSKeywordValue|string $value) {
			$this->setColorSpace($value);
		}
	}

	/** @var list<CSSNumericValue|CSSKeywordValue> */
	public array $channels {
		get => self::exposeChannels($this->channelsValue ?? []);
		set (array $value) {
			$this->setChannels($value);
		}
	}

	/**
	 * @param list<CSSNumericValue|CSSKeywordValue|int|float> $channels
	 */
	public function __construct(
		CSSKeywordValue|string $colorSpace,
		array $channels,
		CSSNumericValue|CSSKeywordValue|int|float $alpha = 1.0,
	) {
		$this->setColorSpace($colorSpace);
		$this->setChannels($channels);
		$this->alphaValue = self::normalizePercentChannel($alpha, 100.0);
	}

	/**
	 * @internal Fast factory for already-validated, caller-owned channel objects.
	 * @param list<CSSNumericValue|CSSKeywordValue> $channels
	 */
	public static function fromValidated(
		CSSKeywordValue $colorSpace,
		array $channels,
		CSSNumericValue|CSSKeywordValue $alpha,
	): self {
		static $reflection = null;
		$reflection ??= new ReflectionClass(self::class);
		/** @var self $instance */
		$instance = $reflection->newInstanceWithoutConstructor();
		$instance->colorSpaceValue = $colorSpace;
		$instance->channelsValue = $channels;
		$instance->alphaValue = $alpha;

		return $instance;
	}

	public function getColorSpace(): CSSKeywordValue
	{
		return $this->colorSpaceValue->clone();
	}

	public function setColorSpace(CSSKeywordValue|string $colorSpace): void
	{
		$rawInput = $colorSpace instanceof CSSKeywordValue ? $colorSpace->getValue() : $colorSpace;
		if (strlen($rawInput) > CSSParserOptions::DEFAULT_MAX_BYTES) {
			throw new CSSParserLimitException(sprintf('CSS color space exceeds maximum size of %d bytes.', CSSParserOptions::DEFAULT_MAX_BYTES));
		}
		$raw = CSSSyntaxScanner::trimCssWhitespace($rawInput);
		$decoded = CSSSyntaxScanner::decodeIdentAt($raw, 0, strlen($raw));
		if ($decoded !== null && $decoded['end'] === strlen($raw) && str_starts_with($decoded['ident'], '--')) {
			$normalized = CSSStyleDeclaration::normalizePropertyName($raw);
			$this->colorSpaceValue = new CSSKeywordValue($normalized);
			return;
		}

		$lower = ($decoded !== null && $decoded['end'] === strlen($raw))
			? strtolower($decoded['ident'])
			: strtolower($raw);
		if (!isset(self::PREDEFINED_SPACES[$lower])) {
			throw new InvalidArgumentException('Unsupported CSS color space: ' . $raw);
		}
		if ($this->channelsValue !== null && count($this->channelsValue) !== 3) {
			throw new InvalidArgumentException(sprintf('Predefined color space "%s" requires exactly 3 channels.', $lower));
		}
		$this->colorSpaceValue = new CSSKeywordValue($lower);
	}

	/** @return list<CSSNumericValue|CSSKeywordValue> */
	public function getChannels(): array
	{
		return self::exposeChannels($this->channelsValue ?? []);
	}

	/**
	 * @param list<CSSNumericValue|CSSKeywordValue> $channels
	 * @return list<CSSNumericValue|CSSKeywordValue>
	 */
	private static function exposeChannels(array $channels): array
	{
		$result = [];
		foreach ($channels as $channel) {
			$result[] = self::exposeComponent($channel);
		}

		return $result;
	}

	/** @param list<CSSNumericValue|CSSKeywordValue|int|float> $channels */
	public function setChannels(array $channels): void
	{
		if ($channels === []) {
			throw new InvalidArgumentException('CSSColor requires at least one channel.');
		}
		if (count($channels) > self::MAX_CUSTOM_CHANNELS) {
			throw new CSSParserLimitException(sprintf('CSS color() channel count exceeds maximum limit of %d.', self::MAX_CUSTOM_CHANNELS));
		}
		$space = $this->colorSpaceValue->getValue();
		if (isset(self::PREDEFINED_SPACES[$space]) && count($channels) !== 3) {
			throw new InvalidArgumentException(sprintf('Predefined color space "%s" requires exactly 3 channels.', $space));
		}

		$normalized = [];
		foreach ($channels as $channel) {
			$normalized[] = self::normalizeNumberOrPercentChannel($channel);
		}
		$this->channelsValue = $normalized;
	}

	public function __clone(): void
	{
		$this->colorSpaceValue = $this->colorSpaceValue->clone();
		$clonedChannels = [];
		foreach ($this->channelsValue ?? [] as $channel) {
			$clonedChannels[] = $channel->clone();
		}
		$this->channelsValue = $clonedChannels;
		$this->alphaValue = $this->alphaValue->clone();
	}

	public function clone(): static
	{
		return clone $this;
	}

	/** @internal */
	public function getComponentValuesInternal(): array
	{
		$components = $this->channelsValue ?? [];
		$components[] = $this->alphaValue;

		return $components;
	}

	/** @internal */
	public function toBoundedCssTextInternal(?int $maxBytes): string
	{
		$space = (string) $this->colorSpaceValue;
		$used = 7 + strlen($space);
		if ($maxBytes !== null && $used > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS color value exceeds maximum size of %d bytes.', $maxBytes));
		}
		$body = $space;
		$prev = $space;
		foreach ($this->channelsValue ?? [] as $channel) {
			$sep = CSSSyntaxScanner::endsWithBareHexEscape($prev) ? '  ' : ' ';
			$used += strlen($sep);
			if ($maxBytes !== null && $used > $maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS color value exceeds maximum size of %d bytes.', $maxBytes));
			}
			$remaining = $maxBytes !== null ? $maxBytes - $used : null;
			$part = $channel instanceof CSSMathValue
				? $channel->toBoundedCssTextInternal($remaining)
				: (string) $channel;
			$used += strlen($part);
			if ($maxBytes !== null && $used > $maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS color value exceeds maximum size of %d bytes.', $maxBytes));
			}
			$body .= $sep . $part;
			$prev = $part;
		}
		$remainingForAlpha = $maxBytes !== null ? $maxBytes - $used : null;

		return 'color(' . $body . $this->formatAlphaSuffixBounded($remainingForAlpha, $maxBytes) . ')';
	}

	protected function toXyzD65(): array
	{
		$space = $this->colorSpaceValue->getValue();
		if (!isset(self::PREDEFINED_SPACES[$space])) {
			throw new InvalidArgumentException(sprintf('Cannot convert custom color space "%s".', $space));
		}

		$c0 = self::resolveLinearChannel($this->channelsValue[0], 1.0);
		$c1 = self::resolveLinearChannel($this->channelsValue[1], 1.0);
		$c2 = self::resolveLinearChannel($this->channelsValue[2], 1.0);

		[$x, $y, $z] = match ($space) {
			'xyz', 'xyz-d65' => [$c0, $c1, $c2],
			'xyz-d50' => self::xyzD50ToD65($c0, $c1, $c2),
			'srgb-linear' => self::linearSrgbToXyzD65($c0, $c1, $c2),
			'srgb' => self::linearSrgbToXyzD65(self::srgbToLinear($c0), self::srgbToLinear($c1), self::srgbToLinear($c2)),
			'display-p3' => self::linearDisplayP3ToXyzD65(self::srgbToLinear($c0), self::srgbToLinear($c1), self::srgbToLinear($c2)),
			'a98-rgb' => self::linearA98ToXyzD65(self::a98ToLinear($c0), self::a98ToLinear($c1), self::a98ToLinear($c2)),
			'prophoto-rgb' => self::xyzD50ToD65(...self::linearProphotoToXyzD50(self::prophotoToLinear($c0), self::prophotoToLinear($c1), self::prophotoToLinear($c2))),
			'rec2020' => self::linearRec2020ToXyzD65(self::rec2020ToLinear($c0), self::rec2020ToLinear($c1), self::rec2020ToLinear($c2)),
		};

		return [$x, $y, $z, $this->alphaValue->clone()];
	}
}
