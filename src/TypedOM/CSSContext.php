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

	/** @var array<string, float> Cache for valid length unit to-px conversion factors */
	private array $toPxFactors = [];

	/** @var array<string, float> Cache for valid length unit from-px conversion factors */
	private array $fromPxFactors = [];

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
		$this->toPxFactors = [];
		$this->fromPxFactors = [];
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
		$this->toPxFactors = [];
		$this->fromPxFactors = [];
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
		$this->toPxFactors = [];
		$this->fromPxFactors = [];
		return $this;
	}

	public function getToPxFactor(string $unit): float
	{
		$key = strtolower($unit);
		if (isset($this->toPxFactors[$key])) {
			return $this->toPxFactors[$key];
		}

		$factor = $this->computeFactor($key);
		if ($factor === null) {
			return 1.0;
		}
		if (!is_finite($factor) || $factor <= 0.0 || !is_finite(1.0 / $factor)) {
			throw new InvalidArgumentException(sprintf('Unit conversion factor for "%s" overflows finite precision.', $unit));
		}

		return $this->toPxFactors[$key] = $factor;
	}

	public function getFromPxFactor(string $unit): float
	{
		$key = strtolower($unit);
		if (isset($this->fromPxFactors[$key])) {
			return $this->fromPxFactors[$key];
		}

		$factor = $this->computeFactor($key);
		if ($factor === null) {
			return 1.0;
		}
		$inverse = $factor > 0.0 ? 1.0 / $factor : 1.0;
		if (!is_finite($factor) || $factor <= 0.0 || !is_finite($inverse)) {
			throw new InvalidArgumentException(sprintf('Unit conversion factor for "%s" overflows finite precision.', $unit));
		}

		$this->toPxFactors[$key] ??= $factor;
		return $this->fromPxFactors[$key] = $inverse;
	}

	private function computeFactor(string $unit): ?float
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
}
