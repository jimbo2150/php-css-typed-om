<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS `@font-feature-values` rule and its typed feature-value maps.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSFontFeatureValuesRule
 */
final class CSSFontFeatureValuesRule extends CSSGroupingRule
{
	/** @var array<string, string> */
	private const array FEATURE_ACCESSORS = [
		'annotation' => 'annotation',
		'ornaments' => 'ornaments',
		'stylistic' => 'stylistic',
		'styleset' => 'styleset',
		'characterVariant' => 'character-variant',
		'swash' => 'swash',
		'historicalForms' => 'historical-forms',
	];

	/** @var array<string, CSSFontFeatureValuesMap> */
	private array $featureMaps = [];

	public function __construct(string $fontFamily)
	{
		$fontFamily = CSSSyntaxScanner::trimCssWhitespace($fontFamily);
		if ($fontFamily === '') {
			throw new InvalidArgumentException('@font-feature-values requires a font-family list.');
		}
		parent::__construct('font-feature-values', $fontFamily);
	}

	public function getType(): int
	{
		return self::FONT_FEATURE_VALUES_RULE;
	}

	public function getFontFamily(): string
	{
		return $this->prelude;
	}

	public function setFontFamily(string $fontFamily): void
	{
		$fontFamily = CSSSyntaxScanner::trimCssWhitespace($fontFamily);
		if ($fontFamily === '') {
			throw new InvalidArgumentException('@font-feature-values requires a font-family list.');
		}
		CSSSyntaxScanner::assertValidFragment($fontFamily, 'Font family list', [';', '{', '}']);
		$this->prelude = $fontFamily;
	}

	public function getFeatureMap(string $featureType): CSSFontFeatureValuesMap
	{
		$featureType = strtolower(CSSSyntaxScanner::trimCssWhitespace($featureType));
		if (!in_array($featureType, self::FEATURE_ACCESSORS, true)) {
			throw new InvalidArgumentException('Invalid font feature value category: ' . $featureType);
		}
		return $this->featureMaps[$featureType] ??= new CSSFontFeatureValuesMap();
	}

	/** @return array<string, CSSFontFeatureValuesMap> */
	public function getFeatureMaps(): array
	{
		return $this->featureMaps;
	}

	public function getCssText(): string
	{
		return CSSGroupingRule::serializeTreeIteratively($this);
	}

	public function __get(string $name): mixed
	{
		if ($name === 'fontFamily') {
			return $this->getFontFamily();
		}
		if (isset(self::FEATURE_ACCESSORS[$name])) {
			return $this->getFeatureMap(self::FEATURE_ACCESSORS[$name]);
		}

		return parent::__get($name);
	}

	public function __isset(string $name): bool
	{
		return $name === 'fontFamily'
			|| isset(self::FEATURE_ACCESSORS[$name])
			|| parent::__isset($name);
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'fontFamily' && is_string($value)) {
			$this->setFontFamily($value);
			return;
		}
		parent::__set($name, $value);
	}

	public function __clone(): void
	{
		parent::__clone();
		$clonedMaps = [];
		foreach ($this->featureMaps as $category => $map) {
			$clonedMaps[$category] = clone $map;
		}
		$this->featureMaps = $clonedMaps;
	}
}
