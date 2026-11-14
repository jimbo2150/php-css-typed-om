<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;

/**
 * Represents a CSS `@font-palette-values` rule.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSFontPaletteValuesRule
 */
final class CSSFontPaletteValuesRule extends CSSAtRule
{
	public function __construct(string $name, CSSStyleDeclaration $descriptors)
	{
		$normalized = CSSStyleDeclaration::normalizePropertyName($name);
		if (!str_starts_with($normalized, '--')) {
			throw new InvalidArgumentException('@font-palette-values requires a dashed identifier starting with "--".');
		}
		parent::__construct('font-palette-values', $normalized, $descriptors, true);
	}

	public function getNameText(): string
	{
		return $this->prelude;
	}

	public function getFontFamily(): string
	{
		return $this->style?->getPropertyValue('font-family') ?? '';
	}

	public function getBasePalette(): string
	{
		return $this->style?->getPropertyValue('base-palette') ?? '';
	}

	public function getOverrideColors(): string
	{
		return $this->style?->getPropertyValue('override-colors') ?? '';
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'name' => $this->getNameText(),
			'fontFamily' => $this->getFontFamily(),
			'basePalette' => $this->getBasePalette(),
			'overrideColors' => $this->getOverrideColors(),
			default => parent::__get($name),
		};
	}
}
