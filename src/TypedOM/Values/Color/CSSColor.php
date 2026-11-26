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

	/**
	 * @internal Normalizes a CSS `color()` space name into `[canonicalSpace, isPredefined]`.
	 * @return array{0: string, 1: bool}
	 */
	public static function normalizeColorSpaceInternal(string $rawInput): array
	{
		$raw = CSSSyntaxScanner::trimCssWhitespace($rawInput);
		$decoded = CSSSyntaxScanner::decodeIdentAt($raw, 0, strlen($raw));
		if ($decoded !== null && $decoded['end'] === strlen($raw) && str_starts_with($decoded['ident'], '--')) {
			return [CSSStyleDeclaration::normalizePropertyName($raw), false];
		}

		$lower = ($decoded !== null && $decoded['end'] === strlen($raw))
			? strtolower($decoded['ident'])
			: strtolower($raw);
		if (!isset(self::PREDEFINED_SPACES[$lower])) {
			throw new InvalidArgumentException('Unsupported CSS color space: ' . $raw);
		}

		return [$lower, true];
	}

	public function setColorSpace(CSSKeywordValue|string $colorSpace): void
	{
		$rawInput = $colorSpace instanceof CSSKeywordValue ? $colorSpace->getValue() : $colorSpace;
		if (strlen($rawInput) > CSSParserOptions::DEFAULT_MAX_BYTES) {
			throw new CSSParserLimitException(sprintf('CSS color space exceeds maximum size of %d bytes.', CSSParserOptions::DEFAULT_MAX_BYTES));
		}
		[$normalized, $isPredefined] = self::normalizeColorSpaceInternal($rawInput);
		if ($isPredefined && $this->channelsValue !== null && count($this->channelsValue) !== 3) {
			throw new InvalidArgumentException(sprintf('Predefined color space "%s" requires exactly 3 channels.', $normalized));
		}
		$this->colorSpaceValue = CSSKeywordValue::fromValidated($normalized);
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

	/** @internal */
	public function getSpaceNameInternal(): string
	{
		return $this->colorSpaceValue->getValue();
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
		return $this->serializeFunctionChannelsBounded('color', $this->channelsValue ?? [], $maxBytes, (string) $this->colorSpaceValue);
	}

	public function toLab(): CSSLab
	{
		if ($this->colorSpaceValue->getValue() === 'xyz-d50') {
			$x50 = self::resolveLinearChannel($this->channelsValue[0], 1.0);
			$y50 = self::resolveLinearChannel($this->channelsValue[1], 1.0);
			$z50 = self::resolveLinearChannel($this->channelsValue[2], 1.0);
			[$l, $a, $b] = self::xyzD50ToLab($x50, $y50, $z50);

			/** @var CSSLab */
			return self::buildFromFloatsInternal('lab', $l, $a, $b, $this->alphaValue->clone(), CSSColorConversionMeta::extractMissingMask($this));
		}

		return parent::toLab();
	}

	public function toLCH(): CSSLCH
	{
		if ($this->colorSpaceValue->getValue() === 'xyz-d50') {
			$x50 = self::resolveLinearChannel($this->channelsValue[0], 1.0);
			$y50 = self::resolveLinearChannel($this->channelsValue[1], 1.0);
			$z50 = self::resolveLinearChannel($this->channelsValue[2], 1.0);
			[$l, $a, $b] = self::xyzD50ToLab($x50, $y50, $z50);
			$c = hypot($a, $b);
			$h = $c < 1e-12 ? 0.0 : fmod(rad2deg(atan2($b, $a)) + 360.0, 360.0);

			/** @var CSSLCH */
			return self::buildFromFloatsInternal('lch', $l, $c, $h, $this->alphaValue->clone(), CSSColorConversionMeta::extractMissingMask($this));
		}

		return parent::toLCH();
	}

	protected function toSrgbChannels(): array
	{
		$space = $this->colorSpaceValue->getValue();
		if ($space === 'srgb') {
			return [
				self::resolveLinearChannel($this->channelsValue[0], 1.0),
				self::resolveLinearChannel($this->channelsValue[1], 1.0),
				self::resolveLinearChannel($this->channelsValue[2], 1.0),
			];
		}
		if ($space === 'srgb-linear') {
			return [
				self::linearToSrgb(self::resolveLinearChannel($this->channelsValue[0], 1.0)),
				self::linearToSrgb(self::resolveLinearChannel($this->channelsValue[1], 1.0)),
				self::linearToSrgb(self::resolveLinearChannel($this->channelsValue[2], 1.0)),
			];
		}

		return parent::toSrgbChannels();
	}

	protected function toXyzD65(): array
	{
		$space = $this->colorSpaceValue->getValue();
		if (!isset(self::PREDEFINED_SPACES[$space])) {
			throw new InvalidArgumentException(sprintf('Cannot convert custom color space "%s".', $space));
		}

		return self::spaceFloatsToXyzD65Internal(
			$space,
			self::resolveLinearChannel($this->channelsValue[0], 1.0),
			self::resolveLinearChannel($this->channelsValue[1], 1.0),
			self::resolveLinearChannel($this->channelsValue[2], 1.0),
		);
	}
}
