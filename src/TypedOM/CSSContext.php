<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitEnum;

/**
 * Runtime context for CSS unit conversions (font size and viewport dimensions in pixels)
 * and dynamic color resolution (`colorScheme`, `currentColor`).
 */
class CSSContext
{
	private float $fontSize;
	private float $viewportWidth;
	private float $viewportHeight;
	private string $colorScheme;
	private ?CSSColorValue $currentColor;

	/** @var array<string, array{0: float, 1: float}> Cache for [toPx, fromPx] conversion factor pairs */
	private array $factorPairs = [];

	/**
	 * @throws InvalidArgumentException When any context dimension is non-finite or <= 0, or colorScheme is invalid
	 */
	public function __construct(
		float $fontSize = 16.0,
		float $viewportWidth = 960.0,
		float $viewportHeight = 540.0,
		string $colorScheme = 'light',
		?CSSColorValue $currentColor = null,
	) {
		$this->fontSize = self::validateDimension($fontSize, 'fontSize');
		$this->viewportWidth = self::validateDimension($viewportWidth, 'viewportWidth');
		$this->viewportHeight = self::validateDimension($viewportHeight, 'viewportHeight');
		$this->colorScheme = self::validateColorScheme($colorScheme);
		$this->currentColor = $currentColor?->clone();
	}

	public function __clone(): void
	{
		if ($this->currentColor !== null) {
			$this->currentColor = $this->currentColor->clone();
		}
	}

	public function getColorScheme(): string
	{
		return $this->colorScheme;
	}

	/**
	 * @throws InvalidArgumentException When $colorScheme is not 'light' or 'dark'
	 */
	public function setColorScheme(string $colorScheme): self
	{
		$this->colorScheme = self::validateColorScheme($colorScheme);

		return $this;
	}

	public function getCurrentColor(): ?CSSColorValue
	{
		return $this->currentColor?->clone();
	}

	public function setCurrentColor(?CSSColorValue $currentColor): self
	{
		$this->currentColor = $currentColor?->clone();

		return $this;
	}

	public function getFontSize(): float
	{
		return $this->fontSize;
	}

	/**
	 * @throws InvalidArgumentException When $fontSize is non-finite or <= 0
	 */
	public function setFontSize(float $fontSize): self
	{
		$this->fontSize = self::validateDimension($fontSize, 'fontSize');
		$this->factorPairs = [];
		return $this;
	}

	public function getViewportWidth(): float
	{
		return $this->viewportWidth;
	}

	/**
	 * @throws InvalidArgumentException When $viewportWidth is non-finite or <= 0
	 */
	public function setViewportWidth(float $viewportWidth): self
	{
		$this->viewportWidth = self::validateDimension($viewportWidth, 'viewportWidth');
		$this->factorPairs = [];
		return $this;
	}

	public function getViewportHeight(): float
	{
		return $this->viewportHeight;
	}

	/**
	 * @throws InvalidArgumentException When $viewportHeight is non-finite or <= 0
	 */
	public function setViewportHeight(float $viewportHeight): self
	{
		$this->viewportHeight = self::validateDimension($viewportHeight, 'viewportHeight');
		$this->factorPairs = [];
		return $this;
	}

	public function getToPxFactor(string $unit): float
	{
		return $this->getFactorPair($unit)[0];
	}

	public function getFromPxFactor(string $unit): float
	{
		return $this->getFactorPair($unit)[1];
	}

	/** @return array{0: float, 1: float} */
	private function getFactorPair(string $unit): array
	{
		$key = strtolower($unit);
		if (isset($this->factorPairs[$key])) {
			return $this->factorPairs[$key];
		}

		$factor = $this->computeFactor($key);
		if ($factor === null) {
			return [1.0, 1.0];
		}
		$inverse = $factor > 0.0 ? 1.0 / $factor : 1.0;
		if (!is_finite($factor) || $factor <= 0.0 || !is_finite($inverse)) {
			throw new InvalidArgumentException(sprintf('Unit conversion factor for "%s" overflows finite precision.', $unit));
		}

		return $this->factorPairs[$key] = [$factor, $inverse];
	}

	private function computeFactor(string $unit): ?float
	{
		$absFactor = CSSUnitEnum::tryFromSymbol($unit)?->absoluteToPxFactor();
		if ($absFactor !== null) {
			return $absFactor;
		}

		return match ($unit) {
			'em', 'rem', 'ic', 'ric' => $this->fontSize,
			'ex', 'rex', 'ch', 'rch', 'cap', 'rcap' => $this->fontSize / 2.0,
			'lh', 'rlh' => $this->fontSize * 1.2,
			'vw', 'svw', 'lvw', 'dvw', 'vi', 'svi', 'lvi', 'dvi', 'cqw', 'cqi' => $this->viewportWidth / 100.0,
			'vh', 'svh', 'lvh', 'dvh', 'vb', 'svb', 'lvb', 'dvb', 'cqh', 'cqb' => $this->viewportHeight / 100.0,
			'vmin', 'svmin', 'lvmin', 'dvmin', 'cqmin' => min($this->viewportWidth, $this->viewportHeight) / 100.0,
			'vmax', 'svmax', 'lvmax', 'dvmax', 'cqmax' => max($this->viewportWidth, $this->viewportHeight) / 100.0,
			default => null,
		};
	}

	private static function validateDimension(float $value, string $name): float
	{
		if (!is_finite($value) || $value <= 0.0) {
			throw new InvalidArgumentException(sprintf('CSSContext %s must be a finite positive number.', $name));
		}

		return $value;
	}

	private static function validateColorScheme(string $scheme): string
	{
		if (CSSSyntaxScanner::hasControlCharacters($scheme)) {
			throw new InvalidArgumentException('CSSContext colorScheme contains disallowed control characters.');
		}
		$normalized = strtolower(CSSSyntaxScanner::trimCssWhitespace($scheme));
		if ($normalized !== 'light' && $normalized !== 'dark') {
			throw new InvalidArgumentException('CSSContext colorScheme must be "light" or "dark".');
		}

		return $normalized;
	}
}
