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
 * @property string $family
 * @property string $source
 * @property-read string $status
 * @property string $style
 * @property string $weight
 * @property string $stretch
 * @property string $unicodeRange
 * @property string $variant
 * @property string $featureSettings
 * @property string $variationSettings
 * @property string $display
 * @property string $ascentOverride
 * @property string $descentOverride
 * @property string $lineGapOverride
 * @property string $sizeAdjust
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

	private string $familyValue = '';
	private string $sourceValue = '';

	/** @var array<string, string> */
	private array $descriptors = self::DEFAULTS;

	/** @var array<string, true> Tracks descriptors explicitly configured by caller/rule */
	private array $explicit = [];

	private ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES;
	private ?int $maxDeclarations = CSSParserOptions::DEFAULT_MAX_DECLARATIONS;
	private ?int $maxDepth = CSSParserOptions::DEFAULT_MAX_DEPTH;
	private ?int $maxMathDepth = CSSParserOptions::DEFAULT_MAX_MATH_DEPTH;

	/**
	 * @param array<string, string> $descriptors
	 * @throws InvalidArgumentException When $family, $source, or any descriptor is invalid
	 */
	public function __construct(
		string $family,
		string $source,
		array $descriptors = [],
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
	) {
		CSSRule::assertValidMaxBytesInternal($maxBytes);
		$this->maxBytes = $maxBytes;
		$this->descriptors = self::DEFAULTS;
		$this->setFamily($family);
		$this->setSource($source);

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

		$face = new self($family, $source, $descriptors, $rule->resolveMaxBytesInternal());
		$face->maxDeclarations = $style->resolveMaxDeclarationsInternal();
		$face->maxDepth = $style->resolveMaxDepthInternal();
		$face->maxMathDepth = $style->resolveMaxMathDepthInternal();
		$face->assertWithinLimits($face->familyValue, $face->sourceValue, $face->descriptors, $face->explicit);

		return $face;
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
		$descriptors->setLimitsInternal(
			$this->maxBytes,
			$this->maxDeclarations,
			$this->maxDepth,
			$this->maxMathDepth,
			true,
		);
		$descriptors->setValidatedProperty('font-family', $this->familyValue);
		$descriptors->setValidatedProperty('src', $this->sourceValue);

		foreach (self::TO_CSS_DESCRIPTOR as $prop => $cssName) {
			if (isset($this->explicit[$prop]) || $this->descriptors[$prop] !== self::DEFAULTS[$prop]) {
				$descriptors->setValidatedProperty($cssName, $this->descriptors[$prop]);
			}
		}

		return new CSSFontFaceRule($descriptors, $this->maxBytes);
	}

	public function getFamily(): string { return $this->familyValue; }
	public function getSource(): string { return $this->sourceValue; }
	public function getStatus(): string { return 'unloaded'; }

	public function setFamily(string $family): void
	{
		$trimmed = self::validateNonEmptyField($family, $this->maxBytes, 'FontFace family', 'FontFace family');
		$this->assertWithinLimits($trimmed, $this->sourceValue, $this->descriptors, $this->explicit);
		$this->familyValue = $trimmed;
	}

	public function setSource(string $source): void
	{
		$trimmed = self::validateNonEmptyField($source, $this->maxBytes, 'FontFace source', 'FontFace source');
		$this->assertWithinLimits($this->familyValue, $trimmed, $this->descriptors, $this->explicit);
		$this->sourceValue = $trimmed;
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
			return $this->familyValue;
		}
		if ($name === 'source') {
			return $this->sourceValue;
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
		$trimmed = self::validateNonEmptyField(
			$value,
			$this->maxBytes,
			sprintf('FontFace descriptor "%s"', $key),
			sprintf('FontFace descriptor "%s"', $key),
		);
		$nextDescriptors = $this->descriptors;
		$nextDescriptors[$key] = $trimmed;
		$nextExplicit = $this->explicit;
		$nextExplicit[$key] = true;
		$this->assertWithinLimits($this->familyValue, $this->sourceValue, $nextDescriptors, $nextExplicit);
		$this->descriptors[$key] = $trimmed;
		$this->explicit[$key] = true;
	}

	private static function validateNonEmptyField(string $value, ?int $maxBytes, string $bytesLabel, string $emptyLabel): string
	{
		if ($maxBytes !== null && strlen($value) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('%s exceeds maximum size of %d bytes.', $bytesLabel, $maxBytes));
		}
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($value);
		if ($trimmed === '') {
			throw new InvalidArgumentException(sprintf('%s cannot be empty.', $emptyLabel));
		}
		CSSStyleDeclaration::validatePropertyValue($trimmed);
		if (str_contains($trimmed, '/*') && CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($trimmed)) === '') {
			throw new InvalidArgumentException(sprintf('%s cannot be empty.', $emptyLabel));
		}

		return $trimmed;
	}

	/**
	 * @param array<string, string> $descriptors
	 * @param array<string, true>   $explicit
	 */
	private function assertWithinLimits(string $family, string $source, array $descriptors, array $explicit): void
	{
		$declCount = 2;
		$bytes = strlen('font-family') + strlen($family) + strlen('src') + strlen($source);
		foreach (self::TO_CSS_DESCRIPTOR as $prop => $cssName) {
			if (isset($explicit[$prop]) || $descriptors[$prop] !== self::DEFAULTS[$prop]) {
				$declCount++;
				$bytes += strlen($cssName) + strlen($descriptors[$prop]);
			}
		}
		if ($this->maxDeclarations !== null && $declCount > $this->maxDeclarations) {
			throw new CSSParserLimitException(sprintf('Maximum CSS declaration limit of %d exceeded.', $this->maxDeclarations));
		}
		if ($this->maxBytes !== null && $bytes > $this->maxBytes) {
			throw new CSSParserLimitException(sprintf('FontFace descriptors exceed maximum size of %d bytes.', $this->maxBytes));
		}
	}
}
