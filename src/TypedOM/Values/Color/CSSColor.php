<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Represents a CSS `color(<colorspace> c1 c2 c3 [/ alpha])` value.
 */
final class CSSColor extends CSSColorValue
{
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

	private CSSKeywordValue $colorSpace;

	/** @var list<CSSNumericValue|CSSKeywordValue> */
	private array $channels;

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
		$this->setAlpha($alpha);
	}

	public function getColorSpace(): CSSKeywordValue
	{
		return $this->colorSpace;
	}

	public function setColorSpace(CSSKeywordValue|string $colorSpace): void
	{
		$raw = trim($colorSpace instanceof CSSKeywordValue ? $colorSpace->getValue() : $colorSpace);
		if (str_starts_with($raw, '--')) {
			$normalized = CSSStyleDeclaration::normalizePropertyName($raw);
			$this->colorSpace = new CSSKeywordValue($normalized);
			return;
		}

		$lower = strtolower($raw);
		if (!isset(self::PREDEFINED_SPACES[$lower])) {
			throw new InvalidArgumentException('Unsupported CSS color space: ' . $raw);
		}
		$this->colorSpace = new CSSKeywordValue($lower);
	}

	/** @return list<CSSNumericValue|CSSKeywordValue> */
	public function getChannels(): array
	{
		return $this->channels;
	}

	/** @param list<CSSNumericValue|CSSKeywordValue|int|float> $channels */
	public function setChannels(array $channels): void
	{
		if ($channels === []) {
			throw new InvalidArgumentException('CSSColor requires at least one channel.');
		}
		$space = $this->colorSpace->getValue();
		if (isset(self::PREDEFINED_SPACES[$space]) && count($channels) !== 3) {
			throw new InvalidArgumentException(sprintf('Predefined color space "%s" requires exactly 3 channels.', $space));
		}

		$normalized = [];
		foreach ($channels as $channel) {
			$normalized[] = self::normalizeNumberOrPercentChannel($channel);
		}
		$this->channels = $normalized;
	}

	public function clone(): static
	{
		return new static(
			$this->colorSpace->clone(),
			array_map(static fn (CSSNumericValue|CSSKeywordValue $c): CSSNumericValue|CSSKeywordValue => $c->clone(), $this->channels),
			$this->alpha->clone(),
		);
	}

	public function __toString(): string
	{
		$serialized = array_map(static fn (CSSNumericValue|CSSKeywordValue $c): string => (string) $c, $this->channels);
		return 'color(' . $this->colorSpace . ' ' . implode(' ', $serialized) . $this->formatAlphaSuffix() . ')';
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'colorSpace' => $this->colorSpace,
			'channels' => $this->channels,
			default => parent::__get($name),
		};
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'colorSpace' && ($value instanceof CSSKeywordValue || is_string($value))) {
			$this->setColorSpace($value);
			return;
		}
		if ($name === 'channels' && is_array($value)) {
			$this->setChannels($value);
			return;
		}
		parent::__set($name, $value);
	}

	protected function toXyzD65(): array
	{
		$space = $this->colorSpace->getValue();
		if (!isset(self::PREDEFINED_SPACES[$space])) {
			throw new InvalidArgumentException(sprintf('Cannot convert custom color space "%s".', $space));
		}

		$c0 = self::resolveLinearChannel($this->channels[0], 1.0);
		$c1 = self::resolveLinearChannel($this->channels[1], 1.0);
		$c2 = self::resolveLinearChannel($this->channels[2], 1.0);

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

		return [$x, $y, $z, $this->alpha->clone()];
	}
}
