<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

final class CSSFontFeatureValuesRule extends CSSGroupingRule
{
	/** @var array<string, CSSFontFeatureValuesMap> */
	private array $featureMaps = [];

	public function __construct(string $fontFamily)
	{
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

	public function getFeatureMap(string $featureType): CSSFontFeatureValuesMap
	{
		if (!preg_match('/^[a-zA-Z][a-zA-Z0-9-]*$/D', $featureType)) {
			throw new \InvalidArgumentException('Invalid font feature value category.');
		}
		return $this->featureMaps[$featureType] ??= new CSSFontFeatureValuesMap();
	}
}
