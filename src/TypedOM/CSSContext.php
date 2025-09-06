<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitEnum;

/**
 * CSSContext provides runtime context for unit conversions, such as font-size and viewport dimensions.
 * This allows accurate conversion of relative units like em, rem, vw, vh.
 */
class CSSContext
{
    /** @var float Font size in pixels (default: 16px) */
    private float $fontSize;

    /** @var float Viewport width in pixels (default: 960px) */
    private float $viewportWidth;

    /** @var float Viewport height in pixels (default: 540px) */
    private float $viewportHeight;

    /** @var array<string, float> Cache for conversion factors */
    private array $factorCache = [];

    /**
     * CSSContext constructor.
     *
     * @param float $fontSize Font size in pixels
     * @param float $viewportWidth Viewport width in pixels
     * @param float $viewportHeight Viewport height in pixels
     */
    public function __construct(float $fontSize = 16.0, float $viewportWidth = 960.0, float $viewportHeight = 540.0)
    {
        $this->fontSize = $fontSize;
        $this->viewportWidth = $viewportWidth;
        $this->viewportHeight = $viewportHeight;
    }

    /**
     * Get the font size in pixels.
     *
     * @return float Font size
     */
    public function getFontSize(): float
    {
        return $this->fontSize;
    }

    /**
     * Set the font size in pixels.
     *
     * @param float $fontSize Font size
     * @return self
     */
    public function setFontSize(float $fontSize): self
    {
    	$this->fontSize = $fontSize;
    	$this->factorCache = []; // Clear cache when context changes
    	return $this;
    }

    /**
     * Get the viewport width in pixels.
     *
     * @return float Viewport width
     */
    public function getViewportWidth(): float
    {
        return $this->viewportWidth;
    }

    /**
     * Set the viewport width in pixels.
     *
     * @param float $viewportWidth Viewport width
     * @return self
     */
    public function setViewportWidth(float $viewportWidth): self
    {
    	$this->viewportWidth = $viewportWidth;
    	$this->factorCache = []; // Clear cache when context changes
    	return $this;
    }

    /**
     * Get the viewport height in pixels.
     *
     * @return float Viewport height
     */
    public function getViewportHeight(): float
    {
        return $this->viewportHeight;
    }

    /**
     * Set the viewport height in pixels.
     *
     * @param float $viewportHeight Viewport height
     * @return self
     */
    public function setViewportHeight(float $viewportHeight): self
    {
    	$this->viewportHeight = $viewportHeight;
    	$this->factorCache = []; // Clear cache when context changes
    	return $this;
    }

    /**
     * Get conversion factor from unit to pixels.
     *
     * @param string $unit The CSS unit
     * @return float Conversion factor (value * factor = pixels)
     */
    public function getToPxFactor(string $unit): float
    {
    	if (isset($this->factorCache[$unit])) {
    		return $this->factorCache[$unit];
    	}
   
    	$factor = $this->__getFactor($unit);
   
    	$this->factorCache[$unit] = $factor;
    	return $factor;
    }

    /**
     * Get conversion factor from pixels to unit.
     *
     * @param string $unit The CSS unit
     * @return float Conversion factor (px * factor = unit value)
     */
    public function getFromPxFactor(string $unit): float
    {
    	$toPx = $this->getToPxFactor($unit);
    	$fromPx = $toPx > 0 ? 1.0 / $toPx : 1.0;
    	$this->factorCache[$unit . '_from'] = $fromPx;
    	return $fromPx;
    }

	private function __getFactor(string $unit): float {
		return match (strtolower($unit)) {
    		'px' => 1.0,
    		'pt' => 1.333,
    		'pc' => 16.0,
    		'in' => 96.0,
    		'cm' => 37.795,
    		'mm' => 3.7795,
    		'em', 'rem' => $this->fontSize,
    		'vw' => $this->viewportWidth / 100.0,
    		'vh' => $this->viewportHeight / 100.0,
    		'vmin' => min($this->viewportWidth, $this->viewportHeight) / 100.0,
    		'vmax' => max($this->viewportWidth, $this->viewportHeight) / 100.0,
    		default => 1.0, // Unknown units return as-is
    	};
	}
}