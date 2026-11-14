<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM;

use InvalidArgumentException;

/**
 * Runtime context for CSS unit conversions (font size and viewport dimensions in pixels).
 */
class CSSContext
{
	private float $fontSize;
	private float $viewportWidth;
	private float $viewportHeight;

	/** @var array<string, float> Cache for conversion factors */
	private array $factorCache = [];

	/**
	 * @throws InvalidArgumentException When any context dimension is non-finite or <= 0
	 */
	public function __construct(float $fontSize = 16.0, float $viewportWidth = 960.0, float $viewportHeight = 540.0)
	{
		$this->fontSize = self::validateDimension($fontSize, 'fontSize');
		$this->viewportWidth = self::validateDimension($viewportWidth, 'viewportWidth');
		$this->viewportHeight = self::validateDimension($viewportHeight, 'viewportHeight');
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
		$this->factorCache = [];
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
		$this->factorCache = [];
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
		$this->factorCache = [];
		return $this;
	}

	public function getToPxFactor(string $unit): float
	{
		$key = strtolower($unit);
		return $this->factorCache[$key] ??= $this->computeFactor($key);
	}

	public function getFromPxFactor(string $unit): float
	{
		$key = strtolower($unit);
		$cacheKey = $key . '_from';
		if (isset($this->factorCache[$cacheKey])) {
			return $this->factorCache[$cacheKey];
		}

		$toPx = $this->getToPxFactor($key);
		$fromPx = $toPx > 0 ? 1.0 / $toPx : 1.0;
		$this->factorCache[$cacheKey] = $fromPx;

		return $fromPx;
	}

	private function computeFactor(string $unit): float
	{
		return match ($unit) {
			'px' => 1.0,
			'pt' => 96.0 / 72.0,
			'pc' => 16.0,
			'in' => 96.0,
			'cm' => 96.0 / 2.54,
			'mm' => 96.0 / 25.4,
			'q' => 96.0 / 101.6,
			'em', 'rem', 'ic', 'ric' => $this->fontSize,
			'ex', 'rex', 'ch', 'rch', 'cap', 'rcap' => $this->fontSize / 2.0,
			'lh', 'rlh' => $this->fontSize * 1.2,
			'vw', 'svw', 'lvw', 'dvw', 'vi', 'svi', 'lvi', 'dvi', 'cqw', 'cqi' => $this->viewportWidth / 100.0,
			'vh', 'svh', 'lvh', 'dvh', 'vb', 'svb', 'lvb', 'dvb', 'cqh', 'cqb' => $this->viewportHeight / 100.0,
			'vmin', 'svmin', 'lvmin', 'dvmin', 'cqmin' => min($this->viewportWidth, $this->viewportHeight) / 100.0,
			'vmax', 'svmax', 'lvmax', 'dvmax', 'cqmax' => max($this->viewportWidth, $this->viewportHeight) / 100.0,
			default => 1.0,
		};
	}

	private static function validateDimension(float $value, string $name): float
	{
		if (!is_finite($value) || $value <= 0.0) {
			throw new InvalidArgumentException(sprintf('CSSContext %s must be a finite positive number.', $name));
		}

		return $value;
	}
}
