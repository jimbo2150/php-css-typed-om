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
		$fontFamily = trim($fontFamily);
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
		$fontFamily = trim($fontFamily);
		if ($fontFamily === '') {
			throw new InvalidArgumentException('@font-feature-values requires a font-family list.');
		}
		CSSSyntaxScanner::assertValidFragment($fontFamily, 'Font family list', [';', '{', '}']);
		$this->prelude = $fontFamily;
	}

	public function getFeatureMap(string $featureType): CSSFontFeatureValuesMap
	{
		$featureType = strtolower(trim($featureType));
		if (!preg_match('/^[a-zA-Z][a-zA-Z0-9-]*$/D', $featureType)) {
			throw new InvalidArgumentException('Invalid font feature value category.');
		}
		return $this->featureMaps[$featureType] ??= new CSSFontFeatureValuesMap();
	}

	public function getCssText(): string
	{
		$blocks = [];
		foreach ($this->featureMaps as $category => $map) {
			if (count($map) === 0) {
				continue;
			}
			$declarations = [];
			foreach ($map as $name => $values) {
				$declarations[] = $name . ': ' . implode(' ', $values) . ';';
			}
			$blocks[] = '@' . $category . ' { ' . implode(' ', $declarations) . ' }';
		}

		foreach ($this->getCssRules() as $rule) {
			$blocks[] = $rule->getCssText();
		}

		return '@font-feature-values ' . $this->prelude . ' { ' . implode(' ', $blocks) . ' }';
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

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'fontFamily' && is_string($value)) {
			$this->setFontFamily($value);
			return;
		}
		parent::__set($name, $value);
	}
}
