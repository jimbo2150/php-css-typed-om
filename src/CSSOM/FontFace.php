<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use Error;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Standalone metadata and descriptor model for a CSS FontFace (CSS Font Loading API).
 *
 * Outside of a browser DOM/network environment, `status` remains `'unloaded'` and
 * font sources are retained as CSS source descriptors without fetching binary data.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/FontFace
 */
final class FontFace
{
	/** @var array<string, string> Default values for FontFace descriptors */
	private const array DEFAULTS = [
		'style' => 'normal',
		'weight' => 'normal',
		'stretch' => 'normal',
		'unicodeRange' => 'U+0-10FFFF',
		'variant' => 'normal',
		'featureSettings' => 'normal',
		'variationSettings' => 'normal',
		'display' => 'auto',
		'ascentOverride' => 'normal',
		'descentOverride' => 'normal',
		'lineGapOverride' => 'normal',
		'sizeAdjust' => '100%',
	];

	/** @var array<string, string> Mapping of FontFace descriptor names to CSS `@font-face` descriptor names (excluding `variant`) */
	private const array TO_CSS_DESCRIPTOR = [
		'style' => 'font-style',
		'weight' => 'font-weight',
		'stretch' => 'font-stretch',
		'unicodeRange' => 'unicode-range',
		'featureSettings' => 'font-feature-settings',
		'variationSettings' => 'font-variation-settings',
		'display' => 'font-display',
		'ascentOverride' => 'ascent-override',
		'descentOverride' => 'descent-override',
		'lineGapOverride' => 'line-gap-override',
		'sizeAdjust' => 'size-adjust',
	];

	private string $family;
	private string $source;

	/** @var array<string, string> */
	private array $descriptors;

	/** @var array<string, true> Tracks descriptors explicitly configured by caller/rule */
	private array $explicit = [];

	/**
	 * @param array<string, string> $descriptors
	 * @throws InvalidArgumentException When $family, $source, or any descriptor is invalid
	 */
	public function __construct(string $family, string $source, array $descriptors = [])
	{
		$this->setFamily($family);
		$this->setSource($source);
		$this->descriptors = self::DEFAULTS;

		foreach ($descriptors as $key => $value) {
			if (!array_key_exists($key, self::DEFAULTS)) {
				throw new InvalidArgumentException('Unknown FontFace descriptor: ' . $key);
			}
			if (!is_string($value)) {
				throw new InvalidArgumentException(sprintf('FontFace descriptor "%s" must be a string.', $key));
			}
			$this->setDescriptor($key, $value);
		}
	}

	/**
	 * Constructs a standalone FontFace from a parsed CSSFontFaceRule.
	 *
	 * @throws InvalidArgumentException When `font-family` or `src` is missing
	 */
	public static function fromCSSFontFaceRule(CSSFontFaceRule $rule): self
	{
		$style = $rule->getStyle();
		$family = $style->getPropertyValue('font-family');
		$source = $style->getPropertyValue('src');
		if ($family === '' || $source === '') {
			throw new InvalidArgumentException('CSSFontFaceRule must declare both font-family and src to construct a FontFace.');
		}

		$descriptors = [];
		foreach (self::TO_CSS_DESCRIPTOR as $prop => $cssName) {
			$value = $style->getPropertyValue($cssName);
			if ($value !== '') {
				$descriptors[$prop] = $value;
			}
		}
		if (!isset($descriptors['stretch'])) {
			$width = $style->getPropertyValue('font-width');
			if ($width !== '') {
				$descriptors['stretch'] = $width;
			}
		}

		return new self($family, $source, $descriptors);
	}

	/**
	 * Converts this FontFace into a CSSFontFaceRule.
	 *
	 * Note: `variant` is a FontFace API property only (not a valid CSS Fonts 4 `@font-face` descriptor)
	 * and is intentionally omitted from the generated CSSFontFaceDescriptors block.
	 */
	public function toCSSFontFaceRule(): CSSFontFaceRule
	{
		$descriptors = new CSSFontFaceDescriptors();
		$descriptors->setProperty('font-family', $this->family);
		$descriptors->setProperty('src', $this->source);

		foreach (self::TO_CSS_DESCRIPTOR as $prop => $cssName) {
			if (isset($this->explicit[$prop]) || $this->descriptors[$prop] !== self::DEFAULTS[$prop]) {
				$descriptors->setProperty($cssName, $this->descriptors[$prop]);
			}
		}

		return new CSSFontFaceRule($descriptors);
	}

	public function getFamily(): string { return $this->family; }
	public function getSource(): string { return $this->source; }
	public function getStatus(): string { return 'unloaded'; }

	public function setFamily(string $family): void
	{
		if (strlen($family) > CSSParserOptions::DEFAULT_MAX_BYTES) {
			throw new CSSParserLimitException(sprintf('FontFace family exceeds maximum size of %d bytes.', CSSParserOptions::DEFAULT_MAX_BYTES));
		}
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($family);
		if ($trimmed === '') {
			throw new InvalidArgumentException('FontFace family cannot be empty.');
		}
		CSSStyleDeclaration::validatePropertyValue($trimmed);
		$this->family = $trimmed;
	}

	public function setSource(string $source): void
	{
		if (strlen($source) > CSSParserOptions::DEFAULT_MAX_BYTES) {
			throw new CSSParserLimitException(sprintf('FontFace source exceeds maximum size of %d bytes.', CSSParserOptions::DEFAULT_MAX_BYTES));
		}
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($source);
		if ($trimmed === '') {
			throw new InvalidArgumentException('FontFace source cannot be empty.');
		}
		CSSStyleDeclaration::validatePropertyValue($trimmed);
		$this->source = $trimmed;
	}

	public function getStyle(): string { return $this->descriptors['style']; }
	public function setStyle(string $value): void { $this->setDescriptor('style', $value); }
	public function getWeight(): string { return $this->descriptors['weight']; }
	public function setWeight(string $value): void { $this->setDescriptor('weight', $value); }
	public function getStretch(): string { return $this->descriptors['stretch']; }
	public function setStretch(string $value): void { $this->setDescriptor('stretch', $value); }
	public function getUnicodeRange(): string { return $this->descriptors['unicodeRange']; }
	public function setUnicodeRange(string $value): void { $this->setDescriptor('unicodeRange', $value); }
	public function getVariant(): string { return $this->descriptors['variant']; }
	public function setVariant(string $value): void { $this->setDescriptor('variant', $value); }
	public function getFeatureSettings(): string { return $this->descriptors['featureSettings']; }
	public function setFeatureSettings(string $value): void { $this->setDescriptor('featureSettings', $value); }
	public function getVariationSettings(): string { return $this->descriptors['variationSettings']; }
	public function setVariationSettings(string $value): void { $this->setDescriptor('variationSettings', $value); }
	public function getDisplay(): string { return $this->descriptors['display']; }
	public function setDisplay(string $value): void { $this->setDescriptor('display', $value); }
	public function getAscentOverride(): string { return $this->descriptors['ascentOverride']; }
	public function setAscentOverride(string $value): void { $this->setDescriptor('ascentOverride', $value); }
	public function getDescentOverride(): string { return $this->descriptors['descentOverride']; }
	public function setDescentOverride(string $value): void { $this->setDescriptor('descentOverride', $value); }
	public function getLineGapOverride(): string { return $this->descriptors['lineGapOverride']; }
	public function setLineGapOverride(string $value): void { $this->setDescriptor('lineGapOverride', $value); }
	public function getSizeAdjust(): string { return $this->descriptors['sizeAdjust']; }
	public function setSizeAdjust(string $value): void { $this->setDescriptor('sizeAdjust', $value); }

	public function __get(string $name): mixed
	{
		if ($name === 'family') {
			return $this->family;
		}
		if ($name === 'source') {
			return $this->source;
		}
		if ($name === 'status') {
			return 'unloaded';
		}
		if (array_key_exists($name, $this->descriptors)) {
			return $this->descriptors[$name];
		}

		throw new Error('Undefined property: ' . static::class . '::$' . $name);
	}

	public function __isset(string $name): bool
	{
		return $name === 'family' || $name === 'source' || $name === 'status' || isset($this->descriptors[$name]);
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'status') {
			throw new Error('Cannot set read-only property ' . static::class . '::$status');
		}
		if (!is_string($value)) {
			throw new Error('Cannot set property ' . static::class . '::$' . $name);
		}
		if ($name === 'family') {
			$this->setFamily($value);
			return;
		}
		if ($name === 'source') {
			$this->setSource($value);
			return;
		}
		if (array_key_exists($name, $this->descriptors)) {
			$this->setDescriptor($name, $value);
			return;
		}

		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}

	private function setDescriptor(string $key, string $value): void
	{
		if (strlen($value) > CSSParserOptions::DEFAULT_MAX_BYTES) {
			throw new CSSParserLimitException(sprintf('FontFace descriptor "%s" exceeds maximum size of %d bytes.', $key, CSSParserOptions::DEFAULT_MAX_BYTES));
		}
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($value);
		if ($trimmed === '') {
			throw new InvalidArgumentException(sprintf('FontFace descriptor "%s" cannot be empty.', $key));
		}
		CSSStyleDeclaration::validatePropertyValue($trimmed);
		$this->descriptors[$key] = $trimmed;
		$this->explicit[$key] = true;
	}
}
